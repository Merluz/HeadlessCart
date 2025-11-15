<?php

namespace HeadlessCart\Product;

if (!defined('ABSPATH')) exit;

/**
 * Registers the product detail endpoint.
 *
 * GET /headlesscart/v1/product/{slug}
 */
class ProductRoutes
{
    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('headlesscart/v1', '/product/(?P<slug>[a-zA-Z0-9\-_]+)', [
                'methods'             => 'GET',
                'callback'            => [ProductController::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'slug' => ['type' => 'string', 'required' => true],
                ],
            ]);
        });
    }
}
