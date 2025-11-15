=== HeadlessCart ===
Contributors: Merluz, headlesscart
Tags: woocommerce, cart, headless, rest-api, ecommerce
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 0.1.0-internal
License: MIT
License URI: https://opensource.org/license/mit/

== Description ==
HeadlessCart is a modern, cookie-less, token-based cart system for WooCommerce.

It replaces WooCommerce’s cookie sessions with:
- Database sessions  
- JWT-signed tokens  
- Clean REST endpoints  
- Zero cookies during API usage  
- Full compatibility with WooCommerce checkout  

Designed for headless frontends such as:
React, Next.js, Vue, Svelte, Flutter, native apps, mobile clients.

This is an **internal development preview (0.1.x-internal)**.  
It is intended for testing, evaluation, and integration work before public release.

== Features ==
* Full WooCommerce cart API  
* Add / remove / update items  
* Zero cookies in REST mode  
* Works with all WC payment gateways  
* JWT-style tokens for session handling  
* Automatic cleanup of expired carts  
* Predictable API responses for frontend apps  
* Checkout bridge → real WooCommerce orders  

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via **WordPress Admin → Plugins**
3. REST endpoints will be available at:
   - `/wp-json/headlesscart/v1/cart`
   - `/wp-json/headlesscart/v1/checkout/*`

== Frequently Asked Questions ==

= Does this replace the WooCommerce cart? =
No. It overrides WooCommerce **only** during REST/API requests.  
Regular website visitors still use the default WooCommerce cart and cookies.

= Is this secure? =
Yes. Sessions are:
- stored in the database  
- signed using HS256 with WordPress salts  
- validated on every request  
- never exposed via cookies  

= Does checkout still work normally? =
Yes.  
`/checkout/woo-init` creates a real WooCommerce order and then re-enables standard WooCommerce session cookies for payment gateways.

== Changelog ==
= 0.1.0-internal =
* Internal development preview  
* API and architecture implemented  
* Not intended for production  
* Requires real-world testing and validation  
