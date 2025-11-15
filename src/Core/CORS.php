<?php

namespace HeadlessCart\Core;

if (!defined('ABSPATH')) exit;

/**
 * CORS
 * ----
 * Provides clean and predictable CORS behavior for headless environments.
 *
 * Features:
 * - Replaces WP default CORS handling
 * - Allows specifying allowed origins
 * - Handles preflight requests
 * - Exposes custom headers needed by headless cart
 *
 * Activated via: CORS::init()
 */
class CORS
{
    /**
     * List of allowed frontend origins.
     * Can be customized before init() call.
     *
     * @var array<string>
     */
    public static array $allowedOrigins = [];

    /**
     * Initialize CORS handling.
     */
    public static function init()
    {
        // Remove WP default CORS filters
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

        // Add our own handler
        add_filter('rest_pre_serve_request', [self::class, 'serve'], 9999);
    }

    /**
     * Apply CORS headers to all REST responses.
     */
    public static function serve($value)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin && in_array($origin, self::$allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
        } else {
            header("Access-Control-Allow-Origin: *");
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-HeadlessCart-Token, X-WC-Store-API-Nonce");
        header("Access-Control-Expose-Headers: X-HeadlessCart-Token, Cart-Token, cart-token, X-WP-Total, X-WP-TotalPages, Link");
        header("Vary: Origin");

        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }

        return $value;
    }
}
