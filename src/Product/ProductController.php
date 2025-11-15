<?php

namespace HeadlessCart\Product;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;

if (!defined('ABSPATH')) exit;

/**
 * Handles the product detail endpoint:
 *
 *   GET /headlesscart/v1/product/{slug}
 *
 * Features:
 * - Retrieves a product by its slug (SEO friendly)
 * - Full product payload for headless frontends
 * - Includes images, gallery, attributes, variations, brand, tags, categories
 * - Includes custom fields (warranty, extended warranty, recommended flag)
 * - 404-safe and fully sanitized
 */
class ProductController
{
    /**
     * Endpoint handler — find product by slug and return full mapped data.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function handle(WP_REST_Request $req)
    {
        $slug = sanitize_title($req->get_param('slug'));

        // Query product by slug
        $query = new WP_Query([
            'name'        => $slug,
            'post_type'   => 'product',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($query->posts)) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }

        $product = wc_get_product($query->posts[0]->ID);

        if (!$product || !$product->get_id()) {
            return new WP_Error('not_found', 'Invalid product', ['status' => 404]);
        }

        return new WP_REST_Response(ProductMapper::full($product));
    }
}
