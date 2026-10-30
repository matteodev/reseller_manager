# Audit and bounded increment — 2026-10-30

Repository: https://github.com/matteodev/reseller_manager
Baseline: `f05aef5`, branch `main`, clean clone. No previous audit, Docker setup,
CI or tests were present in the cloned tree. The prior audit mentioned in the
operating instructions was not available; this is a new source review.

## Findings

Severity is provisional unless reproduced. Source review is not an exploit test.

| Priority | Finding | Evidence / status |
| --- | --- | --- |
| High | Profile menu uses `read`; profile writes lack an explicit capability check and target-role validation | `AdminMenu()` and `pages/reseller-profile.php`; source finding, integration reproduction pending |
| High | Cart special price is formatted with `wc_price()` before `set_price()` and returns from the entire loop | `filter_price()`; source finding, checkout reproduction pending |
| High | Pricing methods do not consistently require an enabled reseller role/status | `apply_discount()`, `filter_price_html()`, `filter_sale_price()`, `filter_price()`; integration reproduction pending |
| High, functional | Signup field names disagree with handler; fiscal and VAT fields share a name | Reproduced in Docker, fixed, regression test passes |
| Medium | Zero global discount prevents special cart pricing; repeated totals are skipped rather than made idempotent | `filter_price()`; source finding only |
| Medium | Catalog rendering mutates regular price, sale filter returns formatted HTML for custom prices | Pricing methods; source finding only |
| Medium | Passwords are passed through text sanitization; scalar input assumptions need review | Signup/login handlers; not corrected or integration-tested |
| Medium | Uninstall queries a nonexistent standard users `role` column and broadly removes metadata | `uninstall()`; source finding only; README cleanup promise needs correction after lifecycle tests |
| Medium | Special-price save replaces the whole map despite paginated product input; nested forms | `pages/reseller-profile.php`; source finding only |
| Unverified | WordPress 6.0+, PHP 7.4+, unspecified WooCommerce compatibility | No integration matrix available |

Nonce checks and escaping exist in multiple paths, but this does not establish
complete input validation, authorization or XSS protection. No complete security
assessment or real reseller/login/approval/B2B checkout test has been performed.

## Ordered plan, recorded before application code changes

1. Security: reproduce profile access and writes with low-privilege accounts;
   require explicit management permissions and validate target reseller users.
   Review action handlers, nonce binding, scalar input and escaping.
2. Correctness: reproduce numeric custom prices, all cart rows, zero discount,
   enabled/disabled/non-reseller status and repeated total calculations.
   Fix registration contract as a separate small increment after reproduction.
3. Tests: first use an isolated PHP contract test, then add a disposable full
   WordPress/WooCommerce/database stack and permission/pricing regression tests.
4. Documentation: distinguish implemented, verified, limitations and roadmap;
   validate lifecycle and version claims before advertising support.
5. GitHub presentation: retain demonstration status, add accurate description and
   topics (`wordpress`, `woocommerce`, `php`, `demo`); verify license coverage and
   add a license file matching GPLv2-or-later metadata. CI follows meaningful tests.

## Completed increment

Added a Docker Compose test service and a focused signup contract regression test.
Corrected frontend field names to match the existing handler, separated fiscal/VAT
fields and corrected the corresponding label targets. No pricing, permissions or
production code paths beyond the signup template were changed.

Validation: before correction `docker compose run --rm tests` exited 1 with
`FAIL: form field names are unique`. After correction it exited 0 with 12 PASS
checks. PHP lint passed for all 14 PHP files in the Docker PHP 8.3 image.
These are function-double tests, not WordPress/WooCommerce integration coverage.

No matcode.dev access, deployment or plugin activation was performed. Any future
production transfer requires completion of authorization/pricing work and isolated
integration validation. Other repositories remain untouched, following the
one-repository-at-a-time rule.
