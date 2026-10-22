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
grep -q 'id="auth-form"' /tmp/zohal-ci-setup.html
grep -q 'minlength="12"' /tmp/zohal-ci-setup.html
status=$(curl -s -o /tmp/zohal-ci-csrf.json -w '%{http_code}' -X POST -b /tmp/zohal-ci-cookies http://127.0.0.1:8093/api/inquiry)
test "$status" = "419"
status=$(curl -s -o /tmp/zohal-ci-get.json -w '%{http_code}' http://127.0.0.1:8093/api/inquiry)
test "$status" = "404"
# Dotenv secret or database file cannot be requested via HTTP.
status=$(curl -s -o /tmp/zohal-ci-db.json -w '%{http_code}' http://127.0.0.1:8093/../src/app.php)
test "$status" = "404"
# Invalid registration must stay in-place for fetch/AJAX and preserve all fields on the client.
csrf=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' /tmp/zohal-ci-setup.html | head -1)
status=$(curl -s -o /tmp/zohal-ci-bad-register.json -w '%{http_code}' -X POST -b /tmp/zohal-ci-cookies \
  -H 'Accept: application/json' \
  --data-urlencode "_csrf=$csrf" --data-urlencode 'username=test_admin' \
  --data-urlencode 'password=short' --data-urlencode 'confirm_password=short' \
  --data-urlencode 'setup_key=bad' http://127.0.0.1:8093/admin/setup)
test "$status" = '422'
grep -q '"ok":false' /tmp/zohal-ci-bad-register.json
status=$(curl -s -o /tmp/zohal-ci-bad-login.json -w '%{http_code}' -X POST -b /tmp/zohal-ci-cookies \
  -H 'Accept: application/json' --data-urlencode "_csrf=$csrf" \
  --data-urlencode 'username=test_admin' --data-urlencode 'password=invalid_password' \
  http://127.0.0.1:8093/admin/login)
test "$status" = '422'
grep -q '"ok":false' /tmp/zohal-ci-bad-login.json
echo 'PASS: HTTP routing, initial setup, AJAX auth errors, CSRF, private paths'
