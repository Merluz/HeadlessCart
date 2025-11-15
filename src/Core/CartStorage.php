<?php

namespace HeadlessCart\Core;

use WC_Product;

if (!defined('ABSPATH')) exit;

/**
 * Handles low-level cart session storage in the database.
 *
 * Responsibilities:
 * - Create table on install
 * - Create new cart sessions
 * - Load/save cart contents by cart_key
 * - Cleanup expired/broken sessions
 *
 * The actual cart payload is stored as JSON and mirrors
 * WooCommerce's internal cart contents array.
 */
class CartStorage
{
    /**
     * Raw table suffix (without prefix).
     */
    const TABLE_SUFFIX = 'headlesscart_carts';

    /**
     * Resolve the full table name with WordPress prefix.
     *
     * @return string
     */
    public static function tableName(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        /**
         * Filter: headlesscart_cart_table_name
         * Allows changing the table name used for cart storage.
         */
        return apply_filters('headlesscart_cart_table_name', $table);
    }

    /**
     * Create the cart table using dbDelta.
     *
     * Called on plugin activation.
     *
     * @return void
     */
    public static function createTable(): void
    {
        global $wpdb;
        $table           = self::tableName();
        $charsetCollate  = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_key VARCHAR(64) NOT NULL UNIQUE,
            cart_value LONGTEXT NOT NULL,
            cart_expiry BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cart_expiry (cart_expiry)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Generate a random cart key (not a token, just an ID).
     *
     * @return string
     */
    public static function generateCartKey(): string
    {
        return 'ck_' . wp_generate_password(40, false, false);
    }

    /**
     * Create a new cart session row in the database.
     *
     * @param int|null $ttl
     * @return array [cart_key, token, value, expiry]
     */
    public static function createSession(?int $ttl = null): array
    {
        global $wpdb;

        $table    = self::tableName();
        $cartKey  = self::generateCartKey();
        $ttl      = $ttl ?? Token::ttl();
        $expiry   = time() + $ttl;

        // At start we just use an empty cart array.
        $value = [];

        $wpdb->insert(
            $table,
            [
                'cart_key'    => $cartKey,
                'cart_value'  => wp_json_encode($value),
                'cart_expiry' => $expiry,
            ],
            ['%s', '%s', '%d']
        );

        $token = Token::generate($cartKey, $ttl);

        return [
            'cart_key' => $cartKey,
            'token'    => $token,
            'value'    => $value,
            'expiry'   => $expiry,
        ];
    }

    /**
     * Load a cart session from the database.
     *
     * @param string $cartKey
     * @return array|null [cart_key, value, expiry] or null if missing/expired
     */
    public static function load(string $cartKey): ?array
    {
        global $wpdb;
        $table = self::tableName();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT cart_key, cart_value, cart_expiry FROM {$table} WHERE cart_key = %s",
                $cartKey
            ),
            ARRAY_A
        );

        if (!$row) {
            error_log("[HeadlessCart] No session found for key={$cartKey}");
            return null;
        }

        if ((int) $row['cart_expiry'] < time()) {
            error_log("[HeadlessCart] Session expired for key={$cartKey}");
            return null;
        }

        $data = json_decode($row['cart_value'], true);
        if (!is_array($data)) {
            $data = [];
        }

        // Rebuild WC_Product objects where possible (optional soft fix)
        foreach ($data as &$item) {
            if (!empty($item['product_id'])) {
                $product = wc_get_product($item['product_id']);
                if ($product && $product instanceof WC_Product) {
                    $item['data'] = $product;
                } else {
                    unset($item['data']);
                }
            }
        }
        unset($item);

        return [
            'cart_key' => $row['cart_key'],
            'value'    => $data,
            'expiry'   => (int) $row['cart_expiry'],
        ];
    }

    /**
     * Persist the cart contents to the database.
     *
     * @param string $cartKey
     * @param array  $cartValue
     * @param bool   $extendTtl
     * @return int New expiry timestamp
     */
    public static function save(string $cartKey, array $cartValue, bool $extendTtl = true): int
    {
        global $wpdb;
        $table = self::tableName();

        $expiry = $extendTtl ? (time() + Token::ttl()) : (time() + HOUR_IN_SECONDS);

        $wpdb->update(
            $table,
            [
                'cart_value'  => wp_json_encode($cartValue),
                'cart_expiry' => $expiry,
            ],
            ['cart_key' => $cartKey],
            ['%s', '%d'],
            ['%s']
        );

        return $expiry;
    }

    /**
     * Cleanup routine:
     * - Remove expired carts
     * - Remove rows with malformed JSON
     * - Remove items pointing to deleted products
     *
     * Intended to be called via WP-Cron.
     *
     * @return void
     */
    public static function cleanup(): void
    {
        global $wpdb;
        $table = self::tableName();
        $now   = time();

        // 1) Delete expired sessions
        $deletedExpired = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE cart_expiry < %d", $now)
        );

        // 2) Soft clean remaining rows
        $rows = $wpdb->get_results("SELECT cart_key, cart_value FROM {$table}", ARRAY_A);

        $deletedBroken   = 0;
        $deletedMissing  = 0;

        foreach ($rows as $row) {
            $key  = $row['cart_key'];
            $data = json_decode($row['cart_value'], true);

            // Malformed JSON → drop entire row
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $wpdb->delete($table, ['cart_key' => $key]);
                $deletedBroken++;
                continue;
            }

            $changed = false;

            if (is_array($data)) {
                foreach ($data as $itemKey => $item) {
                    if (!empty($item['product_id']) && !wc_get_product($item['product_id'])) {
                        unset($data[$itemKey]);
                        $changed = true;
                        $deletedMissing++;
                    }
                }
            }

            if ($changed) {
                $wpdb->update(
                    $table,
                    ['cart_value' => wp_json_encode($data)],
                    ['cart_key' => $key],
                    ['%s'],
                    ['%s']
                );
            }
        }

        error_log(sprintf(
            '[HeadlessCart] Cleanup completed — expired: %d, broken: %d, missing_products: %d',
            (int) $deletedExpired,
            (int) $deletedBroken,
            (int) $deletedMissing
        ));
    }

    /**
     * Helper: create or recover a session from a REST request.
     *
     * - If a valid token header is present and the session exists → reuse it
     * - Otherwise create a brand new session
     *
     * @param \WP_REST_Request $request
     * @return array [cart_key, token, value, expiry]
     */
    public static function getOrCreateFromRequest(\WP_REST_Request $request): array
    {
        $maybeToken = Token::fromRequest($request);

        if ($maybeToken) {
            $verified = Token::verify($maybeToken);

            if (!is_wp_error($verified) && !empty($verified['cart_key'])) {
                $loaded = self::load($verified['cart_key']);

                if ($loaded) {
                    return [
                        'cart_key' => $loaded['cart_key'],
                        'token'    => $maybeToken,
                        'value'    => $loaded['value'],
                        'expiry'   => $loaded['expiry'],
                    ];
                }
            }
        }

        return self::createSession();
    }
}
