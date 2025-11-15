<?php

namespace HeadlessCart\Checkout;

if (!defined('ABSPATH')) exit;

/**
 * Registers headless checkout routes.
 */
class CheckoutRoutes
{
    public static function register(): void
    {
        add_action('rest_api_init', function () {

            // POST /checkout/prepare
            register_rest_route('headlesscart/v1', '/checkout/prepare', [
                'methods'             => 'POST',
                'callback'            => [CheckoutController::class, 'prepare'],
                'permission_callback' => '__return_true',
            ]);

            // POST /checkout/woo-init
            register_rest_route('headlesscart/v1', '/checkout/woo-init', [
                'methods'             => 'POST',
                'callback'            => [CheckoutController::class, 'wooInit'],
                'permission_callback' => '__return_true',
            ]);

            // POST /checkout/confirm (stub)
            register_rest_route('headlesscart/v1', '/checkout/confirm', [
                'methods'             => 'POST',
                'callback'            => [CheckoutController::class, 'confirm'],
                'permission_callback' => '__return_true',
            ]);

            // GET /order/{id} (stub)
            register_rest_route('headlesscart/v1', '/order/(?P<id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [CheckoutController::class, 'getOrder'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
