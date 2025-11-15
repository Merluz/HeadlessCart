<?php

namespace HeadlessCart\Core;

if (!defined('ABSPATH')) exit;

/**
 * AntiCookie
 * ----------
 * Enforces a strict no-cookie policy across all headless REST requests.
 *
 * Goals:
 * - Disable WooCommerce session cookies during REST calls
 * - Disable WordPress authentication cookies in REST
 * - Disable Store API cookies + nonce checks
 * - Remove ANY "Set-Cookie" header emitted by WP/Woo/plugins
 * - Avoid double sessions (cookie session + token session)
 *
 * This does NOT affect:
 * - WooCommerce checkout
 * - WooCommerce cart in normal web mode
 * - Standard frontend usage
 *
 * Activated via: AntiCookie::init()
 */
class AntiCookie
{
    /**
     * Bootstraps all anti-cookie protections.
     */
    public static function init()
    {
        // WooCommerce: disable session cookie for REST
        add_filter('woocommerce_session_use_cookie', [self::class, 'disableWooSessionCookie'], 10, 1);

        // Store API: disable nonce & cookie usage
        add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');
        add_filter('woocommerce_store_api_cookie', '__return_false');

        // WordPress: ensure no authentication cookies are set
        add_filter('rest_authentication_errors', [self::class, 'removeSetCookieHeader']);

        // Remove ALL Set-Cookie headers at send_headers (strongest layer)
        add_action('send_headers', [self::class, 'clearCookiesSendHeaders'], 1000);

        // Also clean Set-Cookie headers at REST dispatch phase
        add_filter('rest_post_dispatch', [self::class, 'clearCookiesRestDispatch'], 999);

        // WooCommerce REST: disable cookie session handling
        add_filter('woocommerce_rest_set_logged_in_cookie', '__return_false');
        add_filter('woocommerce_rest_customer_session', '__return_false');
        add_filter('woocommerce_rest_set_customer_cookie', '__return_false');
    }

    /**
     * Disable WooCommerce's automatic session cookie in REST environments.
     */
    public static function disableWooSessionCookie($use_cookie)
    {
        return (defined('REST_REQUEST') && REST_REQUEST)
            ? false
            : $use_cookie;
    }

    /**
     * Remove Set-Cookie header during REST authentication.
     */
    public static function removeSetCookieHeader($errors)
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            header_remove('Set-Cookie');
        }
        return $errors;
    }

    /**
     * Hard removal of cookies during send_headers.
     * This catches Woo, WP, Store API and plugin cookies.
     */
    public static function clearCookiesSendHeaders()
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return;
        }

        // Clear generic Set-Cookie
        header("Set-Cookie: ");

        // Explicit WooCommerce cookie removals
        header("Set-Cookie: wp_woocommerce_session_=; Max-Age=0; path=/; samesite=None; secure");
        header("Set-Cookie: woocommerce_items_in_cart=; Max-Age=0; path=/; samesite=None; secure");
        header("Set-Cookie: woocommerce_cart_hash=; Max-Age=0; path=/; samesite=None; secure");
    }

    /**
     * Remove Set-Cookie from REST responses.
     */
    public static function clearCookiesRestDispatch($response)
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return $response;
        }

        if (!headers_sent()) {
            header_remove('Set-Cookie');
        }

        if (is_object($response) && property_exists($response, 'headers')) {
            unset($response->headers['Set-Cookie']);
            unset($response->headers['set-cookie']);
        }

        return $response;
    }
}
