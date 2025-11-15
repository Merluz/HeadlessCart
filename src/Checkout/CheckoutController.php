<?php

namespace HeadlessCart\Checkout;

use HeadlessCart\Core\CartStorage;
use HeadlessCart\Core\SessionHandler;
use HeadlessCart\Cart\CartMapper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/**
 * Handles the full headless checkout flow.
 *
 * Endpoints:
 * - POST /checkout/prepare
 * - POST /checkout/woo-init
 * - POST /checkout/confirm   (stub)
 * - GET  /order/{id}          (stub)
 *
 * Design:
 * - Strict token validation
 * - Cart restored via CartStorage
 * - WooCommerce session/cart bootstrapped only inside controller
 * - Order creation fully isolated from headless cart
 */
class CheckoutController
{
    /* ---------------------------------------------------------------
     * INTERNAL UTILITIES
     * --------------------------------------------------------------- */

    /**
     * Extract and validate token, load session from DB.
     *
     * Returns array:
     * [
     *   'cart_key' => string,
     *   'token'    => string,
     *   'value'    => array,
     *   'expiry'   => int
     * ]
     *
     * Or WP_Error
     */
    protected static function getStrictSession(WP_REST_Request $request)
    {
        $token = \HeadlessCart\Core\Token::fromRequest($request);

        if (!$token) {
            return new WP_Error(
                'missing_token',
                'Missing cart token (X-HeadlessCart-Token or Authorization: Bearer ...)',
                ['status' => 401]
            );
        }

        $verify = \HeadlessCart\Core\Token::verify($token);
        if (is_wp_error($verify)) {
            return $verify;
        }

        $cartKey = $verify['cart_key'] ?? '';
        if (!$cartKey) {
            return new WP_Error(
                'invalid_payload',
                'Token payload missing cart_key',
                ['status' => 403]
            );
        }

        $loaded = CartStorage::load($cartKey);
        if (!$loaded) {
            return new WP_Error(
                'cart_not_found',
                'Cart not found or expired',
                ['status' => 404]
            );
        }

        return [
            'cart_key' => $cartKey,
            'token'    => $token,
            'value'    => $loaded['value'],
            'expiry'   => $loaded['expiry'],
        ];
    }

    /**
     * Boot WooCommerce cart environment from headless cart contents.
     */
    protected static function bootWooCart(array $cartContents): \WC_Cart
    {
        WC()->session = new SessionHandler();
        $cart         = new \WC_Cart();

        if (is_array($cartContents) && !empty($cartContents)) {
            $cart->set_cart_contents($cartContents);
        }

        WC()->cart = $cart;

        return $cart;
    }

    /**
     * Inject both legacy + new token headers.
     */
    protected static function withToken(WP_REST_Response $response, ?string $token): WP_REST_Response
    {
        if ($token) {
            $response->header('X-HeadlessCart-Token', $token);
        }
        return $response;
    }

    /* ---------------------------------------------------------------
     * 1) POST /checkout/prepare
     * --------------------------------------------------------------- */

    public static function prepare(WP_REST_Request $request)
    {
        $session = self::getStrictSession($request);
        if (is_wp_error($session)) {
            return $session;
        }

        $cartContents = $session['value'];

        if (isset($cartContents['cart']) && is_array($cartContents['cart'])) {
            $cartContents = $cartContents['cart']; // legacy
        }

        if (empty($cartContents)) {
            return new WP_Error('empty_cart', 'Cart is empty', ['status' => 400]);
        }

        $cart = self::bootWooCart($cartContents);
        $cart->calculate_totals();

        $payload = [
            'ok'                => true,
            'step'              => 'prepare',
            'message'           => 'Cart validated for checkout',
            'cart_key'          => $session['cart_key'],
            'token'             => $session['token'],
            'cart'              => CartMapper::light($cart->get_cart()),
            'shipping_required' => $cart->needs_shipping(),
            'billing_required'  => true,
            'totals'            => [
                'total'        => $cart->get_total('edit'),
                'subtotal'     => $cart->get_subtotal(),
                'total_items'  => $cart->get_cart_contents_count(),
            ],
        ];

        $res = new WP_REST_Response($payload);
        return self::withToken($res, $session['token']);
    }

    /* ---------------------------------------------------------------
     * 2) POST /checkout/woo-init
     * --------------------------------------------------------------- */

    public static function wooInit(WP_REST_Request $request)
    {
        $session = self::getStrictSession($request);
        if (is_wp_error($session)) {
            return $session;
        }

        $cartContents = $session['value'];
        if (isset($cartContents['cart'])) {
            $cartContents = $cartContents['cart'];
        }

        if (empty($cartContents)) {
            return new WP_Error('empty_cart', 'Cart is empty', ['status' => 400]);
        }

        // Billing + shipping
        $params   = $request->get_json_params();
        $billing  = isset($params['billing'])  && is_array($params['billing'])  ? array_map('sanitize_text_field', $params['billing'])   : [];
        $shipping = isset($params['shipping']) && is_array($params['shipping']) ? array_map('sanitize_text_field', $params['shipping']) : [];

        // Restore Woo cart
        $cart = self::bootWooCart($cartContents);
        $cart->calculate_totals();

        // Avoid duplicate orders for same cart_key
        $existing = get_posts([
            'post_type'   => 'shop_order',
            'post_status' => 'any',
            'meta_key'    => '_headlesscart_key',
            'meta_value'  => $session['cart_key'],
            'fields'      => 'ids',
        ]);

        if (!empty($existing)) {
            return new WP_Error(
                'duplicate_order',
                'Order already created for this cart',
                ['status' => 409]
            );
        }

        // Create WooCommerce order
        $order = wc_create_order();

        foreach ($cart->get_cart() as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);

            if ($pid > 0 && $qty > 0) {
                $product = wc_get_product($pid);
                if ($product) {
                    $order->add_product($product, $qty);
                }
            }
        }

        if ($billing) {
            $order->set_address($billing, 'billing');
        }
        if ($shipping) {
            $order->set_address($shipping, 'shipping');
        }

        $order->calculate_totals();
        $order->update_status('pending', 'Created via HeadlessCart checkout');
        $order->save();

        // Tag for tracking
        update_post_meta($order->get_id(), '_headlesscart', 1);
        update_post_meta($order->get_id(), '_headlesscart_key', $session['cart_key']);

        // Enable real WooCommerce session + cookies (needed for gateways)
        WC()->session = new \WC_Session_Handler();
        WC()->session->init();
        WC()->session->set_customer_session_cookie(true);
        WC()->session->set('order_awaiting_payment', $order->get_id());

        // Payment URL
        $checkoutUrl = $order->get_checkout_payment_url();

        // Remove headless cart
        self::clearHeadlessCart($session['cart_key']);

        return new WP_REST_Response([
            'message'      => 'Order successfully created',
            'order_id'     => $order->get_id(),
            'checkout_url' => esc_url_raw($checkoutUrl),
            'total'        => $order->get_total(),
            'currency'     => $order->get_currency(),
            'step'         => 'woo-init',
        ]);
    }

    /* ---------------------------------------------------------------
     * 3) POST /checkout/confirm (not implemented)
     * --------------------------------------------------------------- */

    public static function confirm()
    {
        return new WP_Error(
            'not_implemented',
            'Checkout confirm endpoint not implemented',
            ['status' => 501]
        );
    }

    /* ---------------------------------------------------------------
     * 4) GET /order/{id} (not implemented)
     * --------------------------------------------------------------- */

    public static function getOrder()
    {
        return new WP_Error(
            'not_implemented',
            'Order details endpoint not implemented',
            ['status' => 501]
        );
    }

    /* ---------------------------------------------------------------
     * INTERNAL: delete headless cart entirely
     * --------------------------------------------------------------- */

    protected static function clearHeadlessCart(string $cartKey): void
    {
        global $wpdb;
        $table = CartStorage::tableName();
        $wpdb->delete($table, ['cart_key' => $cartKey], ['%s']);
    }
}
