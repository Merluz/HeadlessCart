<?php

namespace HeadlessCart\Core;

use HeadlessCart\Utils\Base64Url;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

/**
 * Minimal JWT-like token utility for cart sessions.
 *
 * Payload:
 *   {
 *     "cart_key": "ck_...",
 *     "iat": <issued_at_unix>,
 *     "exp": <expires_unix>,
 *     "iss": "<site_url>"
 *   }
 *
 * This is NOT a full JWT implementation, but:
 * - Uses HS256 (HMAC-SHA256)
 * - Uses WordPress salts as secret
 * - Uses base64url-encoded header.payload.signature
 */
class Token
{
    /**
     * Default cart/session TTL (48 hours).
     *
     * @return int
     */
    public static function ttl(): int
    {
        $ttl = 48 * HOUR_IN_SECONDS;

        /**
         * Filter: headlesscart_cart_ttl
         * Allows changing the default cart TTL (in seconds).
         */
        return (int) apply_filters('headlesscart_cart_ttl', $ttl);
    }

    /**
     * Generate a signed token for a given cart key.
     *
     * @param string   $cartKey
     * @param int|null $ttl
     * @return string
     */
    public static function generate(string $cartKey, ?int $ttl = null): string
    {
        $ttl = $ttl ?? self::ttl();
        $now = time();

        $payload = [
            'cart_key' => $cartKey,
            'iat'      => $now,
            'exp'      => $now + $ttl,
            'iss'      => get_site_url(),
        ];

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $h = Base64Url::encode(wp_json_encode($header));
        $p = Base64Url::encode(wp_json_encode($payload));

        $secret = wp_salt('auth');
        $sig    = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        $s      = Base64Url::encode($sig);

        return $h . '.' . $p . '.' . $s;
    }

    /**
     * Verify a signed token and return the payload array,
     * or WP_Error if invalid/expired.
     *
     * @param string $token
     * @return array|WP_Error
     */
    public static function verify(string $token)
    {
        if (!$token || substr_count($token, '.') !== 2) {
            return new WP_Error('headlesscart_invalid_token', 'Malformed token', ['status' => 403]);
        }

        list($h, $p, $s) = explode('.', $token);

        $secret   = wp_salt('auth');
        $expected = Base64Url::encode(hash_hmac('sha256', $h . '.' . $p, $secret, true));

        if (!hash_equals($expected, $s)) {
            return new WP_Error('headlesscart_invalid_signature', 'Invalid token signature', ['status' => 403]);
        }

        $payloadJson = Base64Url::decode($p);
        $payload     = json_decode($payloadJson, true);

        if (!is_array($payload) || empty($payload['cart_key']) || empty($payload['exp'])) {
            return new WP_Error('headlesscart_invalid_payload', 'Invalid token payload', ['status' => 403]);
        }

        if ((int) $payload['exp'] < time()) {
            return new WP_Error('headlesscart_token_expired', 'Token expired', ['status' => 403]);
        }

        return $payload;
    }

    /**
     * Extract a token from common HTTP headers.
     *
     * Supported:
     * - X-HeadlessCart-Token

     * - Cart-Token
     * - Authorization: Bearer <token>
     *
     * @param WP_REST_Request $request
     * @return string|null
     */

        public static function fromRequest(WP_REST_Request $request): ?string
        {
            $headersToCheck = [
                'x-headlesscart-token',
                'cart-token',
                'authorization',
            ];

            foreach ($headersToCheck as $name) {
                $value = $request->get_header($name);
                if (!$value) {
                    continue;
                }

                if (stripos($value, 'bearer ') === 0) {
                    return trim(substr($value, 7));
                }

                return $value;
            }

            return null;
        }
