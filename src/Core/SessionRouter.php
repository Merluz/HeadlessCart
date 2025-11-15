<?php

namespace HeadlessCart\Core;

if (!defined('ABSPATH')) exit;

/**
 * Decides which WooCommerce session handler to use:
 *
 * - Standard Woo frontend (checkout/cart pages): WC_Session_Handler
 * - Headless/API/React/Next.js: HeadlessCart\Core\SessionHandler
 *
 * The decision is based purely on the current request URI.
 */
class SessionRouter
{
    /**
     * Register the routing filter.
     *
     * Should be called after WooCommerce is loaded.
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter('woocommerce_session_handler', [self::class, 'chooseHandler']);
    }

    /**
     * Filter callback for woocommerce_session_handler.
     *
     * @param string $handler
     * @return string
     */
    public static function chooseHandler(string $handler): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        $isWooCheckoutFlow =
            str_contains($uri, 'checkout') ||
            str_contains($uri, 'order-pay') ||
            str_contains($uri, 'order-received') ||
            str_contains($uri, 'cart');

        if ($isWooCheckoutFlow) {
            // Native WooCommerce behaviour (cookie-based)
            return \WC_Session_Handler::class;
        }

        // Headless / API traffic: use our custom handler.
        return SessionHandler::class;
    }
}
