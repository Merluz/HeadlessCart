# HeadlessCart

<p align="center">
  <img src="assets/HeadlessCartLogo.png" width="180" alt="HeadlessCart Logo">
</p>

<h1 align="center">HeadlessCart</h1>

<p align="center">
  Modern, cookie‑less, token‑based cart system for WooCommerce.<br>
  Built for headless frontends: React, Next.js, Vue, Svelte, Flutter, mobile apps.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/status-dev--preview-blue" />
  <img src="https://img.shields.io/badge/WooCommerce-Compatible-purple" />
  <img src="https://img.shields.io/badge/API-REST%20JSON-green" />
  <img src="https://img.shields.io/badge/license-MIT-lightgray" />
</p>

---

##  Project Status

**Stage:** Internal Development (`0.1.x-internal`)  
This version is meant for evaluation and integration testing.

- API surface may still change  
- Not recommended for production  
- Feedback, issues and PRs are very welcome  


##  Overview

**HeadlessCart** replaces WooCommerce’s cookie‑based cart with:

-  **Database sessions**  
-  **JWT‑style tokens**  
-  **Clean REST endpoints** (`/wp-json/headlesscart/v1/*`)  
-  **Zero cookies** in REST mode  
-  **Full WooCommerce checkout compatibility**

Designed for SPA/SSR frontends such as **Next.js**, **React**, or native apps.

---

##  Features

-  Database‑backed sessions  
-  JWT‑like token (HS256, WP salts)  
-  Zero cookies during API usage  
-  Add / remove / update items  
-  Predictable REST API  
-  Automatic cleanup of expired carts  
-  Checkout bridge → WooCommerce orders  
-  Works with any payment gateway  

---

##  Installation

```
/wp-content/plugins/headlesscart/
```

1. Download or clone the repo  
2. Upload to your WordPress install  
3. Activate via **WP Admin → Plugins**

---

##  REST API Endpoints

###  Cart

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/headlesscart/v1/cart` | Create/restore cart |
| `POST` | `/headlesscart/v1/cart/add` | Add product |
| `PATCH` | `/headlesscart/v1/cart` | Batch update quantities |
| `DELETE` | `/headlesscart/v1/cart` | Clear cart |
| `POST` | `/headlesscart/v1/cart/remove-item` | Remove item by key or product ID |
| `POST` | `/headlesscart/v1/cart/add-one` | Increment quantity |
| `POST` | `/headlesscart/v1/cart/remove-one` | Decrement quantity |

---

###  Checkout

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/headlesscart/v1/checkout/prepare` | Validate cart & totals |
| `POST` | `/headlesscart/v1/checkout/woo-init` | Create WooCommerce order |
| `POST` | `/headlesscart/v1/checkout/confirm` | Stub (reserved) |
| `GET` | `/headlesscart/v1/order/{id}` | Stub (reserved) |

---

##  Token Format

```json
{
  "cart_key": "ck_123abc",
  "iat": 1699999999,
  "exp": 1700179999,
  "iss": "https://example.com"
}
```

Signed using **HS256 (HMAC‑SHA256)**  
Secret = `wp_salt('auth')`

Send it via:

```
X-HeadlessCart-Token: <token>
```

Legacy:

```
Authorization: Bearer <token>
```

---

## Release Tags

Each build is tagged for clarity:

- `v0.1.0-internal` — current development preview  
- `v0.2.0-beta` — next milestone (API stabilization)  
- `v1.0.0` — official stable release

Use tags to reference specific versions when reporting issues.


##  Versioning Policy

HeadlessCart follows a staged release approach:

- **0.1.x-internal** — internal previews, fast iteration, breaking changes allowed  
- **0.2.x-beta** — API stabilizing, safe for early adopters  
- **0.9.x-rc** — release candidates, production-ready  
- **1.0.0** — first stable public release

All builds are semver-compliant and tagged accordingly.


##  Contributing

Pull requests are welcome.  
For large changes, please open an issue first.

---

##  License

**MIT License** — free to use, modify, and distribute.

---

##  Author

**Merluz**  
Creator of HeadlessCart  
Passionate about backend engineering.
