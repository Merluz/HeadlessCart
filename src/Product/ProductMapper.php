<?php

namespace HeadlessCart\Product;

if (!defined('ABSPATH')) exit;

/**
 * Maps WooCommerce products into "light" and "full" headless-friendly structures.
 *
 * - light(): used in catalog, related, recommended
 * - full(): used in single product page
 */
class ProductMapper
{
    /**
     * Light product mapping (for catalog lists).
     * Already implemented altrove, lasciamo così.
     */


    /**
     * Full product mapping for detail view.
     *
     * @param \WC_Product $p
     * @return array
     */
    public static function full(\WC_Product $p): array
    {
        $id = (int) $p->get_id();

        // ----------------------------
        // Images (main + gallery)
        // ----------------------------
        $images = [];
        $allIds = array_merge(
            [$p->get_image_id()],
            $p->get_gallery_image_ids() ?: []
        );

        foreach ($allIds as $gid) {
            if (!$gid) continue;
            $images[] = [
                'full'      => wp_get_attachment_image_url($gid, 'full') ?: null,
                'large'     => wp_get_attachment_image_url($gid, 'large') ?: null,
                'medium'    => wp_get_attachment_image_url($gid, 'medium') ?: null,
                'thumbnail' => wp_get_attachment_image_url($gid, 'thumbnail') ?: null,
            ];
        }

        // ----------------------------
        // Taxonomies (category/tag/brand)
        // ----------------------------
        $mapTerms = function ($tax) use ($id) {
            $terms = wp_get_post_terms($id, $tax);
            if (is_wp_error($terms) || empty($terms)) return [];
            return array_map(fn($t) => [
                'id'   => (int) $t->term_id,
                'name' => html_entity_decode($t->name),
                'slug' => $t->slug,
            ], $terms);
        };

        $categories = $mapTerms('product_cat');
        $tags       = $mapTerms('product_tag');
        $brandArr   = $mapTerms('product_brand');
        $brand      = $brandArr[0] ?? null;

        // ----------------------------
        // Attributes
        // ----------------------------
        $attributes = [];
        foreach ($p->get_attributes() as $attrName => $attrObj) {
            $attributes[] = [
                'name'   => wc_attribute_label($attrName),
                'slug'   => $attrName,
                'values' => $attrObj->is_taxonomy()
                    ? wc_get_product_terms($id, $attrName, ['fields' => 'names'])
                    : $attrObj->get_options()
            ];
        }

        // ----------------------------
        // Variations (for variable products)
        // ----------------------------
        $variations = [];
        if ($p->is_type('variable')) {
            foreach ($p->get_children() as $vid) {
                $v = wc_get_product($vid);
                if (!$v) continue;

                $vAttr = [];
                foreach ($v->get_attributes() as $k => $val) {
                    $vAttr[wc_attribute_label($k)] = $val;
                }

                $variations[] = [
                    'id'            => (int) $v->get_id(),
                    'price'         => (float) $v->get_price(),
                    'regular_price' => (float) $v->get_regular_price(),
                    'sale_price'    => (float) $v->get_sale_price(),
                    'stock_status'  => $v->get_stock_status(),
                    'sku'           => $v->get_sku(),
                    'attributes'    => $vAttr,
                    'image'         => $v->get_image_id()
                        ? wp_get_attachment_image_url($v->get_image_id(), 'medium')
                        : null,
                ];
            }
        }

        // ----------------------------
        // Final mapped structure
        // ----------------------------
        return [
            'id'                => $id,
            'name'              => $p->get_name(),
            'slug'              => $p->get_slug(),
            'permalink'         => get_permalink($id),

            'description'       => wpautop($p->get_description()),
            'short_description' => wpautop($p->get_short_description()),

            'price'             => (float) $p->get_price(),
            'regular_price'     => (float) $p->get_regular_price(),
            'sale_price'        => (float) $p->get_sale_price(),

            'stock_status'      => $p->get_stock_status(),
            'stock_quantity'    => $p->managing_stock()
                ? (int) $p->get_stock_quantity()
                : null,

            'sku'               => $p->get_sku(),

            'rating_average'    => (float) $p->get_average_rating(),
            'rating_count'      => (int) $p->get_review_count(),

            'images'            => $images,
            'categories'        => $categories,
            'tags'              => $tags,
            'brand'             => $brand,
            'attributes'        => $attributes,
            'variations'        => $variations,

            // Custom fields (WooCommerce meta)
            'garanzia'          => (string) $p->get_meta('_garanzia'),
            'garanzia_estesa'   => (string) $p->get_meta('_garanzia_estesa'),
            'consigliato'       => $p->get_meta('_consigliato') === 'yes',

            'updated_at'        => get_post_modified_time('c', true, $id),
        ];
    }
}
