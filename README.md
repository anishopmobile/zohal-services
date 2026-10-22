# استعلام‌یار | Zohal Services

A Persian RTL responsive inquiry portal built with **PHP 8.2+**, **Tabler**, HTML, CSS, JavaScript and `fetch` AJAX. Nine service definitions are available. No end-user accounts, payments, or access to admin metrics. Admin-only first-time setup, token encryption, toggles, logs and Jalali reports are included.

## Status and documentation caveat

**Provider-specific integration is deliberately fail-closed.** Zohal's `/documents` public page does not expose the individual endpoint HTTP methods, request field names, authentication format, or response schemas in the accessible page text. None of those are guessed. All nine services appear in the catalog but require verified provider endpoint paths and parameter mappings before public use. The upstream base URL/header/prefix are environment settings only. Until the integration contract is independently confirmed using a real test token, **do not claim any live lookup has been validated**.

Official sources: https://zohal.io/api and https://zohal.io/documents.

## Requirements

- PHP >= 8.2 with `pdo_sqlite`, `sqlite3` (underlying SQLite), `curl`, `sodium`, `openssl`, `mbstring` optional.
- HTTPS in production; web document root **MUST** be `public/`, never project root.
- Writable project `storage/` directory, not served publicly. Shared PHP process must have filesystem permissions.
- CSS/JS of Tabler are CDN-loaded; self-host pinned assets in environments with restrictive CSP/no CDN access. The frontend uses the Tabler RTL stylesheet version 1.6.1.

## Setup

```sh
cp .env.example .env
php -r 'echo bin2hex(random_bytes(32)),PHP_EOL;'
php -r 'echo bin2hex(random_bytes(24)),PHP_EOL;'
# Paste the first into APP_KEY and the second into APP_SETUP_KEY.
# Verify ZOHAL_API_BASE_URL, ZOHAL_AUTH_HEADER, ZOHAL_AUTH_PREFIX from official docs.
mkdir -p storage && chmod 700 storage
php -S 127.0.0.1:8080 -t public public/router.php
```

Visit `/admin/setup` to create the only initial admin; it requires the `APP_SETUP_KEY`. After that, the registration route is permanently closed as long as that admin exists. Log in at `/admin/login`, enter the 40-character token (encrypted in SQLite with Sodium), and configure individual services. To enable a service in production:

1. Obtain its exact path and GET/POST method in the Zohal API **documentation**, along with the real upstream field names. Configure an absolute **path only** in the admin (for example `/v1/some-service` is just a *format example*, **not** a real Zohal endpoint).
2. Set JSON field mappings like `{"national_code":"nationalCode","mobile":"mobileNumber"}` only if the official documentation confirms those names. An empty `{}` forwards the app's internal field names unchanged.
3. Confirm the `ZOHAL_API_BASE_URL` and `ZOHAL_AUTH_*` settings. No outbound requests are sent to arbitrary per-service hosts. External redirects, non-HTTPS URLs and TLS bypass are disabled.
4. Validate with a provider sandbox/approved test identity and enable the service in the panel. **Do not test with another person's private data.**

For a new service, add one service definition to `src/catalog.php` with a new slug and fields. The database seeds it automatically; its route and field mappings are then managed from the panel. Review/extend validation rules before deployment.

## Security/privacy

- Admin password hashes via `password_hash`, one-time setup secret, SQLite atomic setup, session rotation, HttpOnly/SameSite cookies, CSRF protection, login rate limiting, escaping and CSP.
- Strong APP_KEY used for authenticated secretbox encryption of the provider token. **Keep .env and DB backed up securely together**; losing APP_KEY makes the token unreadable.
- Anonymous rate limit of 5 requests/minute and 30/hour per IP (hashed, never stored in plain text). Production should add WAF/CAPTCHA, DDoS protection and application monitoring.
- Provider receives user-entered data only on explicit consent. Inquiry log stores **no entered PII or provider response** (only service, timestamp, status, performance, keyed-IP hash). Provider response is returned only to the requester via a non-cached JSON response.
- Data retention: inquiry log is retained until your own policy deletes it. Discuss consent, retention, and provider terms with your legal/compliance team before production use.
- SQLite performs well for modest traffic; migrate to PostgreSQL/MySQL + queues for high-volume request processing.

## Tests

```sh
php -l src/app.php
php -l src/catalog.php
php -l public/index.php
php tests/run.php
```

GitHub Actions also installs required extensions, runs regression tests, starts the PHP dev server and checks HTTP paths, CSRF and first-admin setup. Provider live calls are intentionally not part of CI (no real token/contract). **A passing CI job is not a penetration test or proof that live Zohal endpoints work.**
