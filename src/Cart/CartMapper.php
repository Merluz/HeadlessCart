<?php

namespace HeadlessCart\Cart;

use HeadlessCart\Product\ProductMapper;

if (!defined('ABSPATH')) exit;

/**
 * Maps raw WooCommerce cart line items into a lightweight,
 * frontend-friendly structure.
 *
 * Input:  WC_Cart::get_cart() / CartStorage payload
 * Output: [
 *   [
 *     'key'           => 'abc123',
 *     'product_id'    => 123,
 *     'quantity'      => 2,
 *     'line_total'    => 199.00,
 *     'line_subtotal' => 199.00,
 *     'product'       => <ProductMapper::light(...)>
 *   ],
 *   ...
 * ]
 */
class CartMapper
{
    /**
     * Normalize a single cart item into an array.
     *
     * @param array|object $item
     * @return array
     */
    protected static function normalizeItem($item): array
    {
        if (is_object($item)) {
            $item = (array) $item;
        }

        foreach ($item as $k => $v) {
            if (is_object($v)) {
                $item[$k] = (array) $v;
            }
        }

        return $item;
    }

    /**
     * Map a single raw cart item to a "light" representation.
     *
     * @param array|object $item
     * @return array|null
     */
    public static function mapItem($item): ?array
    {
        $item = self::normalizeItem($item);

        if (!isset($item['product_id'])) {
            // Woo sometimes stores weird rows; ignore them safely.
            return null;
        }

        $productId = (int) $item['product_id'];
        if ($productId <= 0) {
            return null;
        }

        $product = $item['data'] ?? null;
        if (!$product instanceof \WC_Product) {
            $product = wc_get_product($productId);
            if (!$product) {
                return null;
            }
        }

        return [
            'key'           => $item['key'] ?? '',
            'product_id'    => $productId,
            'quantity'      => isset($item['quantity']) ? (int) $item['quantity'] : 1,
            'line_total'    => isset($item['line_total']) ? (float) $item['line_total'] : null,
            'line_subtotal' => isset($item['line_subtotal']) ? (float) $item['line_subtotal'] : null,
            'product'       => ProductMapper::light($product),
        ];
    }

    /**
     * Map an entire cart contents array into a list of light line items.
     *
     * @param array|object $cartItems
     * @return array
     */
    public static function light($cartItems): array
    {
        if (is_object($cartItems)) {
            $cartItems = (array) $cartItems;
        }

        $out = [];

        foreach ($cartItems as $key => $item) {
            // Woo does not always include the line key inside the item.
            if (is_array($item) || is_object($item)) {
                if (is_object($item)) {
                    $item = (array) $item;
                }
                $item['key'] = $key;
            }

            $mapped = self::mapItem($item);
            if ($mapped) {
                $out[] = $mapped;
            }
        }

        return $out;
    }
}
