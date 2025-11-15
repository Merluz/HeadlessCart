<?php

namespace HeadlessCart\Fields;

if (!defined('ABSPATH')) exit;

/**
 * Adds custom WooCommerce product meta fields:
 * - Warranty length (months)
 * - Extended warranty description
 * - “Recommended” product flag
 *
 * This class is fully self-contained and loaded by the plugin bootstrap.
 */
class ProductFields
{
    /**
     * Registers all hooks required to display and save product meta.
     */
    public static function init()
    {
        add_action(
            'woocommerce_product_options_general_product_data',
            [self::class, 'renderFields']
        );

        add_action(
            'woocommerce_admin_process_product_object',
            [self::class, 'saveFields']
        );
    }

    /**
     * Renders custom fields inside:
     * WooCommerce → Product Data → General.
     *
     * Uses the WooCommerce admin helpers (woocommerce_wp_*).
     */
    public static function renderFields()
    {
        echo '<div class="options_group">';

        // Warranty length (months)
        woocommerce_wp_text_input([
            'id'          => '_garanzia',
            'label'       => __('Warranty (months)', 'headlesscart'),
            'type'        => 'number',
            'desc_tip'    => true,
            'description' => __('Standard warranty duration in months.', 'headlesscart'),
        ]);

        // Extended warranty (string)
        woocommerce_wp_text_input([
            'id'          => '_garanzia_estesa',
            'label'       => __('Extended warranty', 'headlesscart'),
            'type'        => 'text',
            'desc_tip'    => true,
            'description' => __('Additional warranty details (e.g. “3 years on-site”).', 'headlesscart'),
        ]);

        // Recommended flag
        woocommerce_wp_checkbox([
            'id'          => '_consigliato',
            'label'       => __('Recommended product?', 'headlesscart'),
            'description' => __('Mark this product as recommended.', 'headlesscart'),
        ]);

        echo '</div>';
    }

    /**
     * Persists custom fields when the product is saved.
     *
     * @param WC_Product $product
     */
    public static function saveFields($product)
    {
        $garanzia       = isset($_POST['_garanzia']) ? sanitize_text_field($_POST['_garanzia']) : '';
        $garanziaEstesa = isset($_POST['_garanzia_estesa']) ? sanitize_text_field($_POST['_garanzia_estesa']) : '';
        $consigliato    = isset($_POST['_consigliato']) ? 'yes' : 'no';

        $product->update_meta_data('_garanzia', $garanzia);
        $product->update_meta_data('_garanzia_estesa', $garanziaEstesa);
        $product->update_meta_data('_consigliato', $consigliato);
    }
}
