#!/usr/bin/env bash
set -euo pipefail
php -S 127.0.0.1:8093 -t public public/router.php >/tmp/zohal-ci-php.log 2>&1 &
pid=$!
trap 'kill "$pid" 2>/dev/null || true' EXIT
sleep 1
curl -fsS -c /tmp/zohal-ci-cookies http://127.0.0.1:8093/ >/tmp/zohal-ci-home.html
grep -q 'استعلام' /tmp/zohal-ci-home.html
curl -fsS -b /tmp/zohal-ci-cookies http://127.0.0.1:8093/admin/setup >/tmp/zohal-ci-setup.html
grep -q 'APP_SETUP_KEY' /tmp/zohal-ci-setup.html
status=$(curl -s -o /tmp/zohal-ci-csrf.json -w '%{http_code}' -X POST -b /tmp/zohal-ci-cookies http://127.0.0.1:8093/api/inquiry)
test "$status" = "419"
status=$(curl -s -o /tmp/zohal-ci-get.json -w '%{http_code}' http://127.0.0.1:8093/api/inquiry)
test "$status" = "404"
# Dotenv secret or database file cannot be requested via HTTP.
status=$(curl -s -o /tmp/zohal-ci-db.json -w '%{http_code}' http://127.0.0.1:8093/../src/app.php)
test "$status" = "404"
echo 'PASS: HTTP routing, RTL landing page, initial-admin form, CSRF, and private path smoke tests'
