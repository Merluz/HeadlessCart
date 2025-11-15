<?php

namespace HeadlessCart\Related;

if (!defined('ABSPATH')) exit;

/**
 * Registers REST routes for related products.
 *
 * Endpoint:
 *   GET /headlesscart/v1/related/{id}
 *
 * Matches products based on shared tags, with optional
 * fallback to random products.
 */
class RelatedRoutes
{
    /**
     * Attaches the route to rest_api_init.
     */
    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('headlesscart/v1', '/related/(?P<id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [RelatedController::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'limit' => [
                        'type'    => 'integer',
                        'default' => 5,
                    ],
                ],
            ]);
        });
    }
}
