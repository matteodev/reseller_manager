#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Always create a new project; never attach these tests to existing application data.
project="reseller-tests-$$"
stack=(docker compose -p "$project" -f compose.integration.yaml)
cleanup() {
    result=$?
    if [ "$result" -ne 0 ]; then "${stack[@]}" logs --tail=60 || true; fi
    "${stack[@]}" down --volumes --remove-orphans
    exit "$result"
}
trap cleanup EXIT
docker compose run --rm tests /app/tests/lint.php
docker compose run --rm tests
"${stack[@]}" up -d --wait wordpress
"${stack[@]}" run --rm cli core install --url=http://wordpress --title=ResellerTests --admin_user=demo_admin --admin_password='Local-test-only-123!' --admin_email=admin@example.test --skip-email
"${stack[@]}" run --rm cli plugin install woocommerce --version=11.2.1 --activate
"${stack[@]}" run --rm cli plugin activate reseller_manager
"${stack[@]}" run --rm cli eval-file wp-content/plugins/reseller_manager/tests/http.php
"${stack[@]}" run --rm cli eval-file wp-content/plugins/reseller_manager/tests/integration.php
