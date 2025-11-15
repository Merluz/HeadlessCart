<?php

namespace HeadlessCart\Recommended;

if (!defined('ABSPATH')) exit;

/**
 * Registers REST routes for recommended products.
 *
 * Endpoint:
 *   GET /headlesscart/v1/recommended
 *
 * Routes are attached to `rest_api_init` by this class.
 */
class RecommendedRoutes
{
    /**
     * Registers the API route with WordPress.
     */
    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('headlesscart/v1', '/recommended', [
                'methods'             => 'GET',
                'callback'            => [RecommendedController::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'limit' => [
                        'type'    => 'integer',
                        'default' => 6,
                    ],
                    'category' => [
                        'type' => 'string',
                    ],
                    'brand' => [
                        'type' => 'string',
                    ],
                    'orderby' => [
                        'type'    => 'string',
                        'default' => 'date',
                    ],
                    'order' => [
                        'type'    => 'string',
                        'default' => 'desc',
                    ],
                ],
            ]);
        });
    }
}
