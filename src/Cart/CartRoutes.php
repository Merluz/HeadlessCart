<?php

namespace HeadlessCart\Cart;

if (!defined('ABSPATH')) exit;

/**
 * Registers REST routes for cart operations.
 *
 * Namespace: headlesscart/v1
 */
class CartRoutes
{
    /**
     * Register routes on rest_api_init.
     *
     * Called from plugin bootstrap (headlesscart.php)
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('rest_api_init', function () {
            // /cart (GET, PATCH, DELETE)
            register_rest_route('headlesscart/v1', '/cart', [
                [
                    'methods'             => 'GET',
                    'callback'            => [CartController::class, 'getCart'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods'             => 'PATCH',
                    'callback'            => [CartController::class, 'updateCart'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods'             => 'DELETE',
                    'callback'            => [CartController::class, 'clearCart'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            // POST /cart/add
            register_rest_route('headlesscart/v1', '/cart/add', [
                'methods'             => 'POST',
                'callback'            => [CartController::class, 'addToCart'],
                'permission_callback' => '__return_true',
            ]);

            // POST /cart/remove-item
            register_rest_route('headlesscart/v1', '/cart/remove-item', [
                'methods'             => 'POST',
                'callback'            => [CartController::class, 'removeItem'],
                'permission_callback' => '__return_true',
            ]);

            // POST /cart/add-one
            register_rest_route('headlesscart/v1', '/cart/add-one', [
                'methods'             => 'POST',
                'callback'            => [CartController::class, 'addOne'],
                'permission_callback' => '__return_true',
            ]);

            // POST /cart/remove-one
            register_rest_route('headlesscart/v1', '/cart/remove-one', [
                'methods'             => 'POST',
                'callback'            => [CartController::class, 'removeOne'],
                'permission_callback' => '__return_true',
            ]);

            // POST /cart/clear (alias of DELETE /cart)
            register_rest_route('headlesscart/v1', '/cart/clear', [
                'methods'             => 'POST',
                'callback'            => [CartController::class, 'clearCartPost'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
