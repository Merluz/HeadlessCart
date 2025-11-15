<?php

namespace HeadlessCart\Cart;

use HeadlessCart\Core\CartStorage;
use HeadlessCart\Core\SessionHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * REST controller for cart operations.
 *
 * Routes (namespace: headlesscart/v1):
 * - GET    /cart
 * - PATCH  /cart
 * - DELETE /cart
 * - POST   /cart/add
 * - POST   /cart/remove-item
 * - POST   /cart/add-one
 * - POST   /cart/remove-one
 * - POST   /cart/clear (alias of DELETE /cart)
 */
class CartController
{
    /**
     * Ensure WooCommerce is available for this request.
     *
     * @return bool
     */
    protected static function ensureWoo(): bool
    {
        return function_exists('WC') && class_exists('\WC_Cart');
    }

    /**
     * Bootstrap a WooCommerce cart instance from a stored session.
     *
     * - Creates a SessionHandler
     * - Creates a WC_Cart
     * - Injects stored cart contents into WC_Cart
     *
     * @param array $session
     * @return \WC_Cart
     */
    protected static function bootCart(array $session): \WC_Cart
    {
        // Session must be available before WC_Cart is constructed.
        WC()->session = new SessionHandler();
        $cart         = new \WC_Cart();

        if (!empty($session['value']) && is_array($session['value'])) {
            $cart->set_cart_contents($session['value']);
        }

        WC()->cart = $cart;

        return $cart;
    }

    /**
     * Attach token headers (new + legacy) to a REST response.
     *
     * @param WP_REST_Response $response
     * @param string|null      $token
     * @return WP_REST_Response
     */
    protected static function withTokenHeader(WP_REST_Response $response, ?string $token): WP_REST_Response
    {
        if ($token) {
            $response->header('X-HeadlessCart-Token', $token);
        }

        return $response;
    }


    /**
     * GET /cart
     *
     * Create or restore a cart session and return its contents.
     */
    public static function getCart(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $session = CartStorage::getOrCreateFromRequest($request);

        $response = [
            'cart_key'    => $session['cart_key'],
            'token'       => $session['token'],
            'cart'        => CartMapper::light($session['value']),
            'items_count' => is_array($session['value']) ? count($session['value']) : 0,
            'expiry'      => $session['expiry'],
        ];

        $res = new WP_REST_Response($response);

        return self::withTokenHeader($res, $session['token']);
    }

    /**
     * POST /cart/add
     *
     * Add a product to the cart:
     * {
     *   "id" | "product_id": 123,
     *   "quantity": 2
     * }
     */
    public static function addToCart(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $params     = $request->get_json_params();
        $productId  = (int) ($params['id'] ?? $params['product_id'] ?? 0);
        $quantity   = (int) ($params['quantity'] ?? 1);

        if ($productId <= 0) {
            return new WP_REST_Response([
                'error' => 'Missing or invalid product ID',
            ], 400);
        }

        if ($quantity <= 0) {
            $quantity = 1;
        }

        $session = CartStorage::getOrCreateFromRequest($request);

        // Load existing cart (if any)
        $existing     = CartStorage::load($session['cart_key']);
        $wasEmpty     = empty($existing) || empty($existing['value']);
        $previousSize = $wasEmpty ? 0 : count($existing['value']);

        $cart = self::bootCart($existing ?? $session);

        $product = wc_get_product($productId);
        if (!$product || !($product instanceof \WC_Product)) {
            return new WP_REST_Response([
                'error' => 'Product not found or invalid',
            ], 404);
        }

        $addedKey = $cart->add_to_cart($productId, $quantity, 0, [], [], $product);
        if (!$addedKey) {
            return new WP_REST_Response([
                'error' => 'Unable to add product to cart',
            ], 500);
        }

        $cart->calculate_totals();
        $cartData = $cart->get_cart();

        CartStorage::save($session['cart_key'], $cartData);

        $newCount = count($cartData);

        $payload = [
            'message'     => 'Product added to cart',
            'cart'        => CartMapper::light($cartData),
            'cart_key'    => $session['cart_key'],
            'token'       => $session['token'],
            'items_count' => $newCount,
        ];

        $res = new WP_REST_Response($payload);
        $res->set_status($wasEmpty ? 201 : 200);

        return self::withTokenHeader($res, $session['token']);
    }

    /**
     * PATCH /cart
     *
     * Batch update quantities by line key:
     * {
     *   "abc123": 2,
     *   "def456": 1
     * }
     */
    public static function updateCart(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $session = CartStorage::getOrCreateFromRequest($request);

        $existing = CartStorage::load($session['cart_key']) ?? [
            'cart_key' => $session['cart_key'],
            'value'    => [],
            'expiry'   => $session['expiry'],
        ];

        $cart = self::bootCart($existing);

        $updates = $request->get_json_params();
        if (!is_array($updates)) {
            return new WP_REST_Response([
                'error' => 'Invalid payload, expected key => quantity map',
            ], 400);
        }

        foreach ($updates as $key => $qty) {
            if (isset($cart->cart_contents[$key])) {
                $qty = (int) $qty;
                $qty = $qty <= 0 ? 1 : $qty;
                $cart->cart_contents[$key]['quantity'] = $qty;
            }
        }

        $cart->calculate_totals();
        $newCart = $cart->get_cart();

        CartStorage::save($session['cart_key'], $newCart);

        $payload = [
            'message'     => 'Cart quantities updated',
            'cart'        => CartMapper::light($newCart),
            'items_count' => count($newCart),
            'token'       => $session['token'],
        ];

        $res = new WP_REST_Response($payload);

        return self::withTokenHeader($res, $session['token']);
    }

    /**
     * DELETE /cart
     *
     * Empty the cart and keep the same session.
     */
    public static function clearCart(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $session = CartStorage::getOrCreateFromRequest($request);

        // Persist empty cart.
        CartStorage::save($session['cart_key'], []);

        $payload = [
            'message'     => 'Cart cleared',
            'cart'        => [],
            'items_count' => 0,
            'cart_key'    => $session['cart_key'],
        ];

        $res = new WP_REST_Response($payload);

        return self::withTokenHeader($res, $session['token']);
    }

    /**
     * POST /cart/clear
     *
     * Alias for DELETE /cart (for clients that only use POST).
     */
    public static function clearCartPost(WP_REST_Request $request)
    {
        return self::clearCart($request);
    }

    /**
     * POST /cart/remove-item
     *
     * Remove a single line from the cart, using either:
     * - "key": "<line_key>"
     * - "id": <product_id>
     */
    public static function removeItem(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $params    = $request->get_json_params();
        $itemKey   = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        $productId = (int) ($params['id'] ?? 0);

        $session  = CartStorage::getOrCreateFromRequest($request);
        $existing = CartStorage::load($session['cart_key']) ?? [
            'cart_key' => $session['cart_key'],
            'value'    => [],
            'expiry'   => $session['expiry'],
        ];

        $cart = self::bootCart($existing);

        // If no key provided, fallback to product_id lookup.
        if (!$itemKey && $productId > 0) {
            foreach ($cart->get_cart() as $key => $item) {
                if ((int) ($item['product_id'] ?? 0) === $productId) {
                    $itemKey = $key;
                    break;
                }
            }
        }

        if (!$itemKey) {
            return new WP_REST_Response([
                'error' => 'Cart item not found',
            ], 404);
        }

        $cart->remove_cart_item($itemKey);
        $cart->calculate_totals();

        $raw  = $cart->get_cart();
        $light = CartMapper::light($raw);

        CartStorage::save($session['cart_key'], $raw);

        $payload = [
            'message'  => 'Item removed from cart',
            'cart'     => $light,
            'cart_key' => $session['cart_key'],
        ];

        $res = new WP_REST_Response($payload);

        return self::withTokenHeader($res, $session['token']);
    }

    /**
     * POST /cart/add-one
     *
     * Increment quantity of a line by 1.
     * Body: { "key": "...", "id": 123 }
     */
    public static function addOne(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $params    = $request->get_json_params();
        $itemKey   = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        $productId = (int) ($params['id'] ?? 0);

        $session  = CartStorage::getOrCreateFromRequest($request);
        $existing = CartStorage::load($session['cart_key']) ?? [
            'cart_key' => $session['cart_key'],
            'value'    => [],
            'expiry'   => $session['expiry'],
        ];

        $cart = self::bootCart($existing);

        if (!$itemKey && $productId > 0) {
            foreach ($cart->get_cart() as $key => $item) {
                if ((int) ($item['product_id'] ?? 0) === $productId) {
                    $itemKey = $key;
                    break;
                }
            }
        }

        if (!$itemKey || !isset($cart->cart_contents[$itemKey])) {
            return new WP_REST_Response([
                'error' => 'Cart item not found',
            ], 404);
        }

        $current = (int) ($cart->cart_contents[$itemKey]['quantity'] ?? 0);
        $newQty  = $current + 1;

        $cart->set_quantity($itemKey, $newQty);
        $cart->calculate_totals();

        $raw   = $cart->get_cart();
        $light = CartMapper::light($raw);

        CartStorage::save($session['cart_key'], $raw);

        $payload = [
            'message'  => sprintf('Quantity increased to %d', $newQty),
            'cart'     => $light,
            'cart_key' => $session['cart_key'],
        ];

        $res = new WP_REST_Response($payload);

        return self::withTokenHeader($res, $session['token']);
    }

    /**
     * POST /cart/remove-one
     *
     * Decrement quantity of a line by 1 (or remove if it would reach 0).
     * Body: { "key": "...", "id": 123 }
     */
    public static function removeOne(WP_REST_Request $request)
    {
        if (!self::ensureWoo()) {
            return new WP_REST_Response([
                'error' => 'WooCommerce not loaded',
            ], 500);
        }

        $params    = $request->get_json_params();
        $itemKey   = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        $productId = (int) ($params['id'] ?? 0);

        $session  = CartStorage::getOrCreateFromRequest($request);
        $existing = CartStorage::load($session['cart_key']) ?? [
            'cart_key' => $session['cart_key'],
            'value'    => [],
            'expiry'   => $session['expiry'],
        ];

        $cart = self::bootCart($existing);

        if (!$itemKey && $productId > 0) {
            foreach ($cart->get_cart() as $key => $item) {
                if ((int) ($item['product_id'] ?? 0) === $productId) {
                    $itemKey = $key;
                    break;
                }
            }
        }

        if (!$itemKey || !isset($cart->cart_contents[$itemKey])) {
            return new WP_REST_Response([
                'error' => 'Cart item not found',
            ], 404);
        }

        $current = (int) ($cart->cart_contents[$itemKey]['quantity'] ?? 0);

        if ($current <= 1) {
            $cart->remove_cart_item($itemKey);
        } else {
            $cart->set_quantity($itemKey, $current - 1);
        }

        $cart->calculate_totals();

        $raw   = $cart->get_cart();
        $light = CartMapper::light($raw);

        CartStorage::save($session['cart_key'], $raw);

        $payload = [
            'message'  => 'Quantity updated',
            'cart'     => $light,
            'cart_key' => $session['cart_key'],
        ];

        $res = new WP_REST_Response($payload);

        return self::withTokenHeader($res, $session['token']);
    }
}
