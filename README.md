# Reseller Manager
Reseller Manager By <a href="https://matcode.dev">matteodev</a>

Donate: <a href="https://www.paypal.me/matteopapparella">PayPal</a>

Suggestions and feedback are welcome! Please report any bugs or issues on the [GitHub repository](https://github.com/matteodev/reseller_manager/issues).

WooCommerce add-on to manage reseller accounts: collect signups/logins via shortcodes, approve accounts, and apply global or product-specific pricing.

## Requirements
- WordPress 6.0+ and WooCommerce active (plugin checks and deactivates itself if WooCommerce is missing)
- PHP 7.4+ recommended

## Installation
1. Copy the `mc-reseller-manager` folder into `wp-content/plugins/`.
2. Activate **Reseller Manager** from the Plugins screen (activation registers the `mpwp_reseller` role).
3. Keep WooCommerce active for the plugin to stay enabled.

## Admin usage
- **Rivenditori menu**: lists reseller users, lets you enable/disable/delete accounts, and open each profile.
- **Reseller profile**: set a per-user global discount (%), assign custom product prices (when the catalog-discount setting is off), and see status/meta. Actions require nonce confirmation; enabling a reseller triggers an email (if created by an admin it includes the password reset link).
- **Settings** (Rivenditori -> Impostazioni): toggle `reseller_show_full_price` to show catalog prices at list price and apply the discount only at checkout; disabling it shows reseller prices immediately and enables product-specific pricing.
- **Uninstall** cleans up the reseller role, reseller metadata, and reseller users.

## Frontend shortcodes
- `[mcwp_reseller_signup]`: signup form; creates a user with role `mpwp_reseller`, status `disabled`, and shows a pending-approval notice.
- `[mcwp_reseller_auth]`: login form for resellers; only works when `mpwp_reseller_status` is `enabled` and redirects to the WooCommerce shop page.
Place the shortcodes on standard WordPress pages (e.g., "Reseller Signup" and "Reseller Login").

## Pricing logic
- Global discount stored in `mpwp_reseller_discount` is applied to the cart totals (fee label: "Sconto Rivenditore").
- When `reseller_show_full_price` is disabled (default), product prices in the catalog/cart reflect reseller discounts; product-specific prices from `mpwp_reseller_product_prices` override the discount.
- When `reseller_show_full_price` is enabled, the catalog shows full price and the discount is applied at checkout; product-specific pricing is not available.

## File map
- `reseller-manager.php`: plugin bootstrap and lifecycle hooks.
- `class/class-reseller-manager.php`: core logic, hooks, pricing filters, asset enqueue.
- `class/class-reseller-table.php`: admin list table implementation.
- `pages/*.php`: admin views and reseller profile.
- `shortcodes/*.php`: frontend forms.
- `assets/css/reseller-manager.css`: basic styling for forms and admin additions.
- `assets/css/rivenditori.css`: styling loaded only for logged-in resellers.

## Notes
- Text domain: `mc-reseller-manager`
- License: GPLv2 or later

## Translations
- PO file: `languages/mc-reseller-manager-en_US.po`
- Generate the MO file with `msgfmt languages/mc-reseller-manager-en_US.po -o languages/mc-reseller-manager-en_US.mo`
- Ensure the plugin loads the text domain with `load_plugin_textdomain('mc-reseller-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');`
