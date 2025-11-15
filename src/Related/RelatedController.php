<?php

namespace HeadlessCart\Related;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Handles the “related products” endpoint:
 *
 *   GET /headlesscart/v1/related/{id}
 *
 * The controller attempts to find products that share at least
 * one tag with the target product. If fewer than {limit} matches
 * are found, the response is filled with random published products
 * as a fallback to maintain consistent list length.
 *
 * Results are cached for 5 minutes.
 */
class RelatedController
{
    /**
     * REST endpoint callback.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function handle(WP_REST_Request $req)
    {
        $id    = (int) $req->get_param('id');
        $limit = max(1, (int) $req->get_param('limit'));

        // Validate target product
        $product = wc_get_product($id);
        if (!$product || !$product->get_id()) {
            return new WP_Error('not_found', 'Product not found.', ['status' => 404]);
        }

        // Cache key
        $cacheKey = 'hc_related_' . md5($id . '_' . $limit);
        if ($cached = get_transient($cacheKey)) {
            return rest_ensure_response(json_decode($cached, true));
        }

        /**
         * Retrieve product tags.
         * Those tags determine the “relatedness”.
         */
        $tags = array_filter(
            wp_get_post_terms($id, 'product_tag', ['fields' => 'ids'])
        );

        $related = [];

        /**
         * Query products sharing at least one tag.
         */
        if (!empty($tags)) {
            $args = [
                'status'   => 'publish',
                'return'   => 'objects',
                'limit'    => -1,
                'tag'      => $tags,
                'exclude'  => [$id],
                'orderby'  => 'date',
                'order'    => 'DESC',
            ];

            $related = wc_get_products($args);

            // Keep only the needed amount
            if (count($related) > $limit) {
                $related = array_slice($related, 0, $limit);
            }
        }

        /**
         * Fallback: fill missing slots with random products.
         * Ensures consistent list size.
         */
        if (count($related) < $limit) {
            $need = $limit - count($related);

            $fallback = wc_get_products([
                'status'  => 'publish',
                'return'  => 'objects',
                'exclude' => array_merge([$id], array_map(fn($p) => $p->get_id(), $related)),
                'limit'   => $need,
                'orderby' => 'rand',
            ]);

            $related = array_merge($related, $fallback);
        }

        /**
         * Convert to lightweight API structure.
         */
        $related = array_map(
            ['HeadlessCart\\Product\\ProductMapper', 'light'],
            $related
        );

        $response = [
            'product_id' => $id,
            'total'      => count($related),
            'data'       => $related,
        ];

        // Cache for 5 minutes
        set_transient($cacheKey, json_encode($response), 5 * MINUTE_IN_SECONDS);

        return new WP_REST_Response($response);
    }
}
