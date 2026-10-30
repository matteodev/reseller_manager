# Reseller Manager

A demonstration WooCommerce add-on by [matteodev](https://matcode.dev) for reseller
signup, account approval and dedicated prices. This is an educational project,
not production-ready software or a hosted service.

Read the [technical case study with screenshots and test evidence](docs/case-study/README.md).

## Implemented features

- Signup and login forms through `[mcwp_reseller_signup]` and `[mcwp_reseller_auth]`,
  also exposed as Gutenberg blocks.
- Pending reseller accounts (`mpwp_reseller` role) and administrative approval.
- Administrative global discounts and product-specific prices.
- A catalog/cart discount mode and a full-catalog-price mode using a discount fee.

## Installation and administration

1. Use an isolated WordPress environment with WooCommerce active.
2. Copy this directory to `wp-content/plugins/reseller_manager` and activate it.
3. Put the shortcodes or blocks on WordPress pages.
4. Use **Rivenditori** to create, approve, disable or delete reseller accounts.
   Profile access and mutations require `manage_options`; deletion additionally
   checks the target user's deletion capability. Only reseller users are targets.
5. Use **Rivenditori → Impostazioni** to choose pricing behavior.

When `reseller_show_full_price` is off, approved resellers receive numeric custom
product prices or the global percentage discount. Custom prices also work with a
zero global discount. `reseller_apply_regular_price` selects the regular price
rather than the current sale price as the global-discount base in this mode.
Totals can be recalculated without compounding the discount or changing the
product's regular price. These prices are recomputed from persisted product data.

When `reseller_show_full_price` is on, the catalog stays at public prices and a
negative fee applies the global discount to the cart subtotal. Product-specific
prices are inactive in this mode. This fee is not a WooCommerce coupon; complex
coupon, tax and shipping combinations require separate validation.

Signup accounts remain pending until approved. Only enabled users with the reseller
role can log in through the reseller form or receive reseller pricing. The standard
WordPress login remains governed by WordPress; pending accounts are not globally
locked out of WordPress authentication.

Uninstall removes this plugin's exact metadata keys, its two options and its role.
It **preserves WordPress accounts and content**, assigning `subscriber` when removing
the reseller role leaves an account without another role. Deleting an account from
the administration screen is a separate, explicit action.

## Run all checks

Requirements: Docker Engine, Docker Compose and Bash. Run from the repository root:

```sh
bash tests/run.sh
```

The runner creates a new Docker project with dedicated WordPress/database volumes,
installs WooCommerce **11.2.1**, runs all suites and removes only its temporary project
and volumes on exit. It publishes no ports and mounts this repository read-only.
Initial image/plugin downloads need internet access. Compose images are pinned by
digest; local database credentials are test-only placeholders.

Available checks:

| Suite | Scope |
| --- | --- |
| `tests/signup.php` | 12 form/handler contract assertions with WordPress doubles |
| `tests/integration.php` | 29 cases using actual WordPress, WooCommerce and MariaDB |
| `tests/http.php` | 7 HTTP cases for rendered forms, nonce rejection, signup, pending/approved login and cookies |
| `tests/lint.php` | PHP syntax across all repository PHP files |

Quick isolated contract check: `docker compose run --rm tests`.
Syntax check: `docker compose run --rm tests /app/tests/lint.php`.
Full integration uses `compose.integration.yaml`; its database is disposable.
Never point these test scripts at an existing site or production database.

## Verified configuration and limits

The tested stack is WordPress **7.0**, WooCommerce **11.2.1**, MariaDB **10.11**,
PHP **8.3.33** for CLI integration and PHP **8.2.31** for HTTP requests.
The older README's WordPress 6.0+/PHP 7.4+ statements were not backed by a tested
matrix and are not compatibility guarantees.

Tests cover unauthorized profile reads/writes, target-role validation, approval,
nonce rejection, numeric multi-row pricing, repeated totals, zero discounts,
disabled/non-reseller exclusion, paginated price saves, password characters,
shortcode/block output, malformed frontend values and safe uninstall cleanup.
Email sending is intercepted in controller tests; actual email delivery and reset
links are not verified. The integration suite invokes uninstall cleanup directly,
not WordPress's complete plugin-deletion UI.

Not yet verified: multisite lifecycle, older WordPress/PHP/WooCommerce versions,
a full browser regression suite, variable/grouped price ranges, variation-specific pricing UI,
Store API/Checkout Blocks, advanced tax/coupon/shipping combinations, external
pricing plugins, large reseller datasets and a comprehensive security review.
The classic cart tests use simple products. Variable/grouped catalog range HTML
is left to WooCommerce. Recomputing prices from persisted data may conflict with
other plugins that change prices dynamically.

## Roadmap and project details

Next: extend the compatibility/test matrix, cover variable products and Store API,
validate taxes/coupons and email delivery.
The [Docker tests workflow](.github/workflows/docker-tests.yml) now runs the same
local runner on push and pull requests and preserves its output. Its syntax has
been validated with actionlint; check GitHub Actions after pushing for the remote result.
See [audit history and current findings](docs/AUDIT.md). Suggested GitHub topics:
`wordpress`, `woocommerce`, `php`, `demo`.

- Entry point: `reseller-manager.php`; core: `class/class-reseller-manager.php`.
- Administrative pages: `pages/`; forms: `shortcodes/`; blocks: `blocks/`.
- Text domain: `mc-reseller-manager`; translations: `languages/`.
- License: [GPLv2 or later (GPL-2.0-or-later)](LICENSE), matching the existing plugin metadata.
- [Report issues](https://github.com/matteodev/reseller_manager/issues).
