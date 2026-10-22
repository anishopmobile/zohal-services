# استعلام‌یار | Zohal Services — PHP + MySQL

Persian RTL inquiry portal with Tabler. Nine catalog services and a private administrative area, AJAX forms and a 30-day optional remember-me login.

## Install on cPanel (main domain, no `/public` in URL)

**Downloads:** The two version-matched archives are **committed under `dist/`** so they can be downloaded directly from this repository. Every GitHub Actions run also builds and publishes the same source-consistent archives under its Artifacts section (`zohal-cpanel-<commit-sha>`). They are created by `python3 scripts/build_cpanel.py`.

1. Use PHP **8.2+** with `pdo_mysql`, `curl`, `sodium`, and `openssl`. Enable HTTPS and connect the domain to the cPanel hosting server. Create a **MySQL database** and dedicated **MySQL user** in cPanel → MySQL Databases; assign the user **ALL PRIVILEGES** for that database. The database must exist before first use.
2. In cPanel File Manager, go to the home directory (the parent of `public_html`). Extract `zohal-cpanel-private.zip` there. It must create `~/zohal-services/src/app.php` and `~/zohal-services/.env.example` (NOT inside `public_html`).
3. Extract `zohal-cpanel-public_html.zip` directly inside `~/public_html` with `index.php`, `.htaccess` and `assets/`. The deployment `index.php` requires the private app through `../zohal-services/public/index.php`; the private package contains this original public PHP entrypoint. **Do not expose the project root or `.env` under the web document root.**
4. Copy `~/zohal-services/.env.example` to `~/zohal-services/.env`. Generate APP_KEY (32 random bytes / **64 hex characters**) and APP_SETUP_KEY (min 16 random characters) independently:
   ```bash
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'
   ```
   Enter the generated values, plus **DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD** from cPanel. Prefix database/user names with the cPanel account prefix exactly as shown in MySQL Databases. Passwords containing `#` or `=` should be enclosed in double quotes in `.env`.
5. Set file permissions to keep `.env` private (e.g. `chmod 600 ~/zohal-services/.env`) and ensure PHP can read `src` and write required session data. MySQL tables are created automatically on the first request. Visit `https://YOUR-DOMAIN/admin/setup` to create the first administrator with APP_SETUP_KEY; then use `/admin/login`.
6. The application **does not need MySQL imports** on a fresh install. Existing SQLite data is **not automatically migrated**. If you need to preserve SQLite records, back them up and migrate them separately *before switching production*.

### Troubleshooting “خطای پیکربندی”

Usually indicates incorrect MySQL credentials, missing `pdo_mysql`, malformed `APP_KEY`, missing database grants, or wrong split-host layout. Check cPanel → MultiPHP Manager, Select PHP Version/Extensions and the PHP **error log** (no DB credentials are displayed to visitors). `APP_SETUP_KEY` alone does not establish database connectivity. Verify the files are at `~/zohal-services/src/app.php`, `~/zohal-services/public/index.php`, and `~/public_html/index.php`.

### Development server

```bash
cp .env.example .env
# Configure DB_* and secure APP_KEY/APP_SETUP_KEY in .env.
php -S 127.0.0.1:8080 -t public public/router.php
```

### Security and account behavior

- Initial-admin-only registration (once the first administrator exists, registration is closed). Admin credentials and lookup statistics are stored in **MySQL/InnoDB**, passwords hashed using `password_hash`.
- Failed login/registration with JavaScript keeps form fields on screen (AJAX, no reload); non-JavaScript submissions preserve the username only, not passwords or setup secrets. Password length and confirmation are checked on blur/submit without page refresh.
- “به خاطر سپردن ورود” is optional for **30 days**, using randomly generated cookie credentials whose SHA-256 hashes alone are stored in MySQL, rotated on session restoration and revoked on logout. HTTPS is required for production use. Do not use shared computers with remember-me checked.
- Provider token is sealed with Sodium under APP_KEY and stored in the settings table; keep **both MySQL backups and APP_KEY** safe. Inquiry logs store no entered identity/banking data and no raw IPs. Protect the MySQL account and `.env`.
- Rate-limiting is serialized per hashed IP with MySQL GET_LOCK; CSRF, secure sessions, CSP, HTTPS upstream and server-side input validation are in place. Add WAF/monitoring for production. The app does not auto-import old SQLite databases.

### Provider integration

The exact Zohal HTTP paths, fields, and authentication headers are **not verified** from the accessible public documentation. The services are fail-closed until individually configured against the official contract and tested with approved credentials/test data. Do **not** treat passing unit/HTTP tests as validation of the real Zohal service.

Sources: https://zohal.io/api and https://zohal.io/documents.

### Tests and distributions

```bash
php tests/run.php  # when DB_* and APP_KEY are set, tests MySQL, security helpers and token rotation
bash tests/http-smoke.sh
python3 scripts/build_cpanel.py
python3 -m unittest discover -s tests -p 'test_*.py' -v
```

GitHub Actions runs `php -l`, MySQL 8 integration, HTTP smoke tests, ZIP consistency checks and uploads host-ready ZIP archives as workflow artifacts on every push. Both ready-to-install ZIPs are included in the same Git commit under `dist/`; the workflow validates their internal files against the source snapshot.
