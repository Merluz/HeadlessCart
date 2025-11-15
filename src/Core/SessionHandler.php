<?php

namespace HeadlessCart\Core;

use WC_Session_Handler;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

/**
 * Bridge between WooCommerce's session system and HeadlessCart's DB sessions.
 *
 * Behaviour:
 * - Frontend Woo (checkout, cart, etc.): use native WC_Session_Handler
 *   (configured via SessionRouter, not here).
 * - REST / headless requests:
 *   - No cookies
 *   - Session resolved by token + CartStorage
 *   - Cart contents stored in headlesscart_carts table
 */
class SessionHandler extends WC_Session_Handler
{
    /**
     * Current cart key for this request.
     *
     * @var string|null
     */
    protected $cartKey;

    /**
     * Current signed token associated with the session.
     *
     * @var string|null
     */
    protected $token;

    /**
     * Expiry timestamp of the current cart/session.
     *
     * @var int|null
     */
    protected $expiry;

    public function __construct()
    {
        parent::__construct();

        // Avoid inheriting any legacy data from cookies.
        $this->_data = [];
    }

    /**
     * Initialize the session.
     *
     * WooCommerce calls this on every request.
     * Here we decide whether we are in headless/REST mode or not.
     *
     * - Non-REST: fallback to parent (cookie-based)
     * - REST: use token + CartStorage and NEVER call parent::init_session()
     */
    public function init_session()
    {
        $isRest = defined('REST_REQUEST') && REST_REQUEST;

        // Woo standard flow (checkout, cart pages, etc.)
        if (!$isRest) {
            parent::init_session();
            return;
        }

        // Headless / REST mode:
        // No cookie-based session — read token + DB session only.
        $server  = rest_get_server();
        $request = $server instanceof \WP_REST_Server
            ? $server->get_current_request()
            : null;

        if (!$request instanceof WP_REST_Request) {
            // Last-resort fallback: create empty session.
            $session = CartStorage::createSession();
        } else {
            $session = CartStorage::getOrCreateFromRequest($request);
        }

        $this->cartKey = $session['cart_key'];
        $this->token   = $session['token'] ?? null;
        $this->expiry  = $session['expiry'] ?? null;

        // Load session data into Woo's internal structure.
        $this->_data = $session['value'] ?? [];

        // Woo expects this to be true to avoid trying to create its own cookie.
        $this->_has_cookie = true;
    }

    /**
     * Retrieve a session by ID.
     *
     * Used internally by Woo — we proxy it to CartStorage.
     *
     * @param string $customer_id
     * @param bool   $default_value
     * @return array
     */
    public function get_session($customer_id, $default_value = false)
    {
        $row = CartStorage::load((string) $customer_id);

        if ($row) {
            return $row['value'];
        }

        return $default_value ?: [];
    }

    /**
     * Persist the session data to HeadlessCart storage.
     *
     * @param string $old_session_key
     * @return void
     */
    public function save_data($old_session_key = '')
    {
        if (!$this->cartKey) {
            return;
        }

        CartStorage::save($this->cartKey, $this->_data);
    }

    /**
     * Returns the current session ID.
     *
     * In headless mode this matches the cart_key.
     *
     * @return string|null
     */
    public function get_session_id()
    {
        return $this->cartKey ?: parent::get_session_id();
    }

    /**
     * Get the current token associated with this session.
     * Useful for controllers that want to echo it back in headers.
     *
     * @return string|null
     */
    public function get_token(): ?string
    {
        return $this->token;
    }

    /**
     * Get the current expiry timestamp for this session.
     *
     * @return int|null
     */
    public function get_expiry(): ?int
    {
        return $this->expiry;
    }
}
