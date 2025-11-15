<?php

namespace HeadlessCart\Recommended;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/**
 * Handles the “recommended products” endpoint:
 *
 *   GET /headlesscart/v1/recommended
 *
 * Returns a collection of WooCommerce products where the
 * custom product meta `_consigliato` equals "yes".
 *
 * Supports filtering by:
 * - category
 * - brand (WooCommerce attribute)
 * - sorting (date | popularity | price)
 * - limit
 *
 * Uses a 5-minute transient cache to reduce query overhead.
 */
class RecommendedController
{
    /**
     * REST endpoint callback.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function handle(WP_REST_Request $req)
    {
        $limit    = max(1, (int) ($req->get_param('limit') ?? 6));
        $category = sanitize_text_field($req->get_param('category') ?? '');
        $brand    = sanitize_text_field($req->get_param('brand') ?? '');
        $orderby  = sanitize_text_field($req->get_param('orderby') ?? 'date');
        $order    = strtolower($req->get_param('order') ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Build cache key
        $cacheKey = 'hc_recommended_' . md5(json_encode([
            $limit, $category, $brand, $orderby, $order
        ]));

        // Return cached result if available
        if ($cached = get_transient($cacheKey)) {
            return rest_ensure_response(json_decode($cached, true));
        }

        /**
         * Base product query.
         * The `_consigliato = yes` meta flag marks recommended products.
         */
        $args = [
            'status'     => 'publish',
            'limit'      => $limit,
            'meta_key'   => '_consigliato',
            'meta_value' => 'yes',
            'return'     => 'objects',
            'orderby'    => 'date',
            'order'      => $order,
        ];

        // Sorting overrides
        switch ($orderby) {
            case 'price':
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = '_price';
                break;

            case 'popularity':
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = 'total_sales';
                break;

            // default: date
        }

        // Optional category filter
        if ($category) {
            $args['category'] = [$category];
        }

        // Optional brand filter (WooCommerce attribute taxonomy)
        if ($brand) {
            $args['attribute']      = 'product_brand';
            $args['attribute_term'] = $brand;
        }

        // Query products
        $products = wc_get_products($args);

        // Transform using plugin-wide product mapper
        $data = array_map(
            ['HeadlessCart\\Product\\ProductMapper', 'light'],
            $products
        );

        $response = [
            'total' => count($data),
            'data'  => $data,
        ];

        // Cache for 5 minutes
        set_transient($cacheKey, json_encode($response), 5 * MINUTE_IN_SECONDS);

        return new WP_REST_Response($response);
    }
}
