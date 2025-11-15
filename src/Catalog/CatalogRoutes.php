<?php

namespace HeadlessCart\Catalog;

if (!defined('ABSPATH')) exit;

/**
 * Registers the catalog endpoint.
 *
 * GET /headlesscart/v1/catalog
 */
class CatalogRoutes
{
    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('headlesscart/v1', '/catalog', [
                'methods'             => 'GET',
                'callback'            => [CatalogController::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'page'        => ['type' => 'integer', 'default' => 1],
                    'per_page'    => ['type' => 'integer', 'default' => 20],
                    'category'    => ['type' => 'string'],
                    'tag'         => ['type' => 'string'],
                    'brand'       => ['type' => 'string'],
                    'search'      => ['type' => 'string'],
                    'min_price'   => ['type' => 'number'],
                    'max_price'   => ['type' => 'number'],
                    'orderby'     => ['type' => 'string', 'default' => 'date'],
                    'order'       => ['type' => 'string', 'default' => 'desc'],
                    'in_stock'    => ['type' => 'boolean', 'default' => false],
                    'consigliato' => ['type' => 'boolean', 'default' => false],
                ],
            ]);
        });
    }
}
