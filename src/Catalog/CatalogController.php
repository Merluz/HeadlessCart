<?php

namespace HeadlessCart\Catalog;

use WP_REST_Request;
use WP_REST_Response;
use HeadlessCart\Product\ProductMapper;

if (!defined('ABSPATH')) exit;

/**
 * Handles the product catalog endpoint:
 *
 *   GET /headlesscart/v1/catalog
 *
 * Supports:
 * - Pagination (page, per_page)
 * - Category, tag, brand filtering
 * - Price filters (min_price, max_price)
 * - Search (title, SKU, slug)
 * - Sorting (date, title, price, popularity)
 * - In-stock only
 * - Recommended-only
 *
 * Uses hybrid filtering:
 *   A) Native WooCommerce pagination when no search/price filters
 *   B) PHP-side filtering for search and price-range queries
 *
 * Responses are cached for 5 minutes.
 */
class CatalogController
{
    /**
     * REST endpoint handler.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function handle(WP_REST_Request $req)
    {
        // ---------- Input parsing ----------
        $page     = max(1, (int) $req->get_param('page'));
        $perPage  = max(1, (int) $req->get_param('per_page'));

        $category = sanitize_text_field($req->get_param('category') ?? '');
        $tag      = sanitize_text_field($req->get_param('tag') ?? '');
        $brand    = sanitize_text_field($req->get_param('brand') ?? '');

        $searchRaw = sanitize_text_field($req->get_param('search') ?? '');
        $search    = trim(strtolower($searchRaw));

        $minPrice = $req->get_param('min_price');
        $maxPrice = $req->get_param('max_price');

        $orderby = sanitize_text_field($req->get_param('orderby') ?? 'date');
        $order   = strtolower($req->get_param('order')) === 'asc' ? 'ASC' : 'DESC';

        $inStock     = filter_var($req->get_param('in_stock'), FILTER_VALIDATE_BOOLEAN);
        $recommended = filter_var($req->get_param('consigliato'), FILTER_VALIDATE_BOOLEAN);

        // Determine if WooCommerce can handle pagination
        $hasPriceFilter  = is_numeric($minPrice) || is_numeric($maxPrice);
        $hasSearchFilter = $search !== '';
        $usePhpFiltering = $hasPriceFilter || $hasSearchFilter;

        // ---------- Cache ----------
        $cacheKey = 'hc_catalog_' . md5(json_encode([
            $page, $perPage, $category, $tag, $brand, $search,
            $minPrice, $maxPrice, $orderby, $order, $inStock, $recommended
        ]));

        if ($cached = get_transient($cacheKey)) {
            return rest_ensure_response(json_decode($cached, true));
        }

        // ---------- Base Woo Query ----------
        $args = [
            'status'  => 'publish',
            'return'  => 'objects',
            'orderby' => 'date',
            'order'   => 'DESC',
        ];

        if ($category) $args['category'] = [$category];
        if ($tag)      $args['tag']      = [$tag];
        if ($inStock)  $args['stock_status'] = 'instock';

        // Sorting
        switch ($orderby) {
            case 'price':
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order']    = $order;
                break;

            case 'title':
                $args['orderby'] = 'title';
                $args['order']   = $order;
                break;

            case 'popularity':
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = 'total_sales';
                $args['order']    = $order;
                break;

            default:
                $args['orderby'] = 'date';
                $args['order']   = $order;
        }

        // Recommended filter
        if ($recommended) {
            $args['meta_query'] = [
                [
                    'key'     => '_consigliato',
                    'value'   => 'yes',
                    'compare' => '=',
                ]
            ];
        }

        // Brand filter
        if ($brand) {
            $args['attribute']      = 'product_brand';
            $args['attribute_term'] = $brand;
        }

        // ============================================================
        //  BRANCH A — native Woo pagination (fast path)
        // ============================================================
        if (!$usePhpFiltering) {

            $args['limit']  = $perPage;
            $args['offset'] = ($page - 1) * $perPage;

            $products = wc_get_products($args);

            // Count total
            $countArgs = $args;
            unset($countArgs['limit'], $countArgs['offset'], $countArgs['meta_key']);
            $countArgs['return'] = 'ids';

            $allIds = wc_get_products($countArgs);

            $total       = is_array($allIds) ? count($allIds) : 0;
            $totalPages  = max(1, (int) ceil($total / $perPage));

            $data = array_map([ProductMapper::class, 'light'], $products);
        }

        // ============================================================
        //  BRANCH B — PHP-side filtering (search/price)
        // ============================================================
        else {

            $args['limit'] = -1;
            unset($args['offset']);

            $allProducts = wc_get_products($args);

            // PHP filtering
            $filtered = array_filter($allProducts, function($p) use ($search, $minPrice, $maxPrice, $hasPriceFilter, $hasSearchFilter) {

                /** @var \WC_Product $p */
                $price = (float) $p->get_price();

                // Price filter
                if ($hasPriceFilter) {
                    if (is_numeric($minPrice) && $price < (float) $minPrice) return false;
                    if (is_numeric($maxPrice) && $price > (float) $maxPrice) return false;
                }

                // Search filter
                if ($hasSearchFilter) {
                    $haystack = strtolower(
                        $p->get_name() . ' ' .
                        $p->get_sku()  . ' ' .
                        $p->get_slug()
                    );

                    if (strpos($haystack, $search) === false) return false;
                }

                return true;
            });

            $filtered     = array_values($filtered);
            $total        = count($filtered);
            $totalPages   = max(1, (int) ceil($total / $perPage));

            $offset       = ($page - 1) * $perPage;
            $pageItems    = array_slice($filtered, $offset, $perPage);

            $data = array_map([ProductMapper::class, 'light'], $pageItems);
        }

        // ---------- Build response ----------
        $response = [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
            'has_next'    => $page < $totalPages,
            'has_prev'    => $page > 1,
            'data'        => $data,
            'updated_at'  => current_time('c'),
        ];

        // Cache result
        set_transient($cacheKey, json_encode($response), 5 * MINUTE_IN_SECONDS);

        return new WP_REST_Response($response);
    }
}
