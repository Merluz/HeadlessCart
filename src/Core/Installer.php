<?php

namespace HeadlessCart\Core;

if (!defined('ABSPATH')) exit;

/**
 * Handles plugin install/uninstall tasks:
 *
 * - Creates the cart table
 * - Schedules/un-schedules daily cleanup
 * - Registers the cleanup action hook
 */
class Installer
{
    /**
     * Hook entry point.
     *
     * Called from the plugin bootstrap (headlesscart.php)
     * on every request to ensure the cleanup action is registered.
     *
     * @return void
     */
    public static function init(): void
    {
        // Register the cleanup action handler.
        add_action('headlesscart_cart_cleanup_event', [CartStorage::class, 'cleanup']);
    }

    /**
     * Called on plugin activation.
     *
     * @return void
     */
    public static function activate(): void
    {
        // Ensure table exists.
        CartStorage::createTable();

        // Schedule daily cleanup if not already scheduled.
        if (!wp_next_scheduled('headlesscart_cart_cleanup_event')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'headlesscart_cart_cleanup_event');
        }
    }

    /**
     * Called on plugin deactivation.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        $ts = wp_next_scheduled('headlesscart_cart_cleanup_event');
        if ($ts) {
            wp_unschedule_event($ts, 'headlesscart_cart_cleanup_event');
        }
    }
}
