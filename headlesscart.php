<?php
/**
 * Plugin Name: HeadlessCart
 * Description: Modern Headless Cart for WooCommerce — DB sessions, JWT tokens, no cookies, clean REST API.
 * Version: 0.1.0-dev
 * Author: Merluz
 * License: MIT
 */


if (!defined('ABSPATH')) exit;

// Composer autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

use HeadlessCart\Core\Installer;
use HeadlessCart\Core\AntiCookie;
use HeadlessCart\Core\CORS;
use HeadlessCart\Core\SessionRouter;

// ---- Activation / Deactivation ----

register_activation_hook(__FILE__, [Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [Installer::class, 'deactivate']);

// ---- Initialization ----

add_action('plugins_loaded', function () {

    // CORS for REST / headless clients
    CORS::init();

    // Anti-cookie layer for REST / Store API
    AntiCookie::init();

    // Install / cleanup hooks
    Installer::init();
});

// WooCommerce-dependent pieces
add_action('woocommerce_init', function () {
    // Route Woo sessions to our handler for headless traffic
    SessionRouter::init();

    HeadlessCart\Cart\CartRoutes::register();
    HeadlessCart\Product\ProductRoutes::register();
    HeadlessCart\Catalog\CatalogRoutes::register();
    HeadlessCart\Related\RelatedRoutes::register();
    HeadlessCart\Recommended\RecommendedRoutes::register();
    HeadlessCart\Contact\ContactRoutes::register();
    HeadlessCart\Checkout\CheckoutRoutes::register();

}, 5);

// Woo admin product fields
add_action('init', function () {
    HeadlessCart\Fields\ProductFields::init();
});
