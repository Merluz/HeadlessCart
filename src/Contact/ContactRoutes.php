<?php

namespace HeadlessCart\Contact;

if (!defined('ABSPATH')) exit;

/**
 * Registers the contact form endpoint.
 *
 * POST /headlesscart/v1/contact/send
 */
class ContactRoutes
{
    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('headlesscart/v1', '/contact/send', [
                'methods'  => 'POST',
                'callback' => [ContactController::class, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
