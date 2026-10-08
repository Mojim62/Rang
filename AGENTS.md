# AGENTS.md — Bamero (WordPress + WooCommerce)

Notes for working on this repo inside the Base44 sandbox. Run the app with the
sandbox compose file, **not** the repo's own `docker-compose.yml`.

## Stack shape (non-obvious)

- The repo ships **only** `wp-content` (theme `bamero` + 6 plugins) and
  `wp-config.php` / `.htaccess` / `health-check.php`. **WordPress core is not in the
  repo** — it comes from the `wordpress:php8.3-apache` image. `docker-compose.yml`
  bind-mounts `./` over the whole docroot, which would hide core; that is why a
  separate compose file exists.
- **WooCommerce is not vendored** either. It is installed from wordpress.org by the
  one-shot `setup` service. It, uploads and WP core all live in the `bamero_html`
  Docker volume — never in the repo, so the git checkout stays clean.
- `wp-config.php` is **fail-closed**: it exits with HTTP 500 unless `DB_NAME`,
  `DB_USER`, `DB_PASSWORD`, `DB_HOST` and the 8 WordPress keys/salts are present. It
  reads real env vars first, then a `.env` file at `/var/www/.env` (i.e.
  `dirname(ABSPATH)`).
- Default table prefix is `wp_bamero_` (`TABLE_PREFIX`).

## Running it

```bash
docker compose -f docker-compose.base44.yml up -d
```

`wordpress` serves the app on host port 3000; `db` is MySQL 8.0 (host port not
published); `setup` is a one-shot job that runs `wp core install`, installs and
activates WooCommerce, activates the theme + Bamero plugins, then `chown`s
`wp-content` back to uid 33 and exits.

Platform secrets arrive through `env_file: /run/base44/app.env` (salts/keys +
`BAMERO_INTERNAL_ID_SALT`). Local DB credentials are generated in the compose file.
To change source code, edit the repo — theme/plugins are bind-mounted and picked up
on the next request (PHP, no rebuild).

## Sandbox-only overrides (in `docker-compose.base44.yml`, nothing shared changed)

The web service starts with `env > /var/www/.env` and enables `mod_headers`:

- `SetEnv HTTPS on` + `RequestHeader set X-Forwarded-Proto "https" early`
  (`/etc/apache2/conf-enabled/zz-base44-proxy.conf`). The preview proxy terminates
  TLS and forwards plain HTTP, so without this WordPress emits `http://` asset URLs
  that the browser blocks on the https preview page, and the repo's own `.htaccess`
  HTTPS redirect (`RewriteCond %{HTTP:X-Forwarded-Proto} !https`) would loop.
  Enabling `mod_headers` also makes the `.htaccess` security headers effective; they
  are inside `<IfModule>` guards and were previously skipped.
- `/var/www/.env` is written from the process environment because mod_php does not
  reliably expose container env vars via `getenv()`.

Outside the sandbox (normal PHP hosting with real HTTPS) neither is needed.

## How to verify

```bash
# app responds and emits https assets
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:3000/
curl -sS http://localhost:3000/ | grep -oE '(href|src)="https?://[^"]+' | sort -u | head

# app's own health gate (run through the setup service so theme/plugins are mounted)
docker compose -f docker-compose.base44.yml run --rm --no-deps \
  -v "$PWD/tests:/tmp/tests:ro" --entrypoint sh setup \
  -c 'cd /var/www/html && wp eval-file /tmp/tests/health_check.php --allow-root'

# CI production gate (needs bash + php → use the Debian-based image)
docker run --rm --user root -v "$PWD:/app" -w /app \
  --entrypoint bash wordpress:php8.3-apache -c 'bash tests/production_gate.sh'
```

## Known pre-existing quirks (not caused by the sandbox)

- `tests/health_check.php` always reports 2 false FAILs: `get('Text Domain')` should
  be `get('TextDomain')`, and the uploads check tests `'' === $upload['error']` while
  `wp_upload_dir()` returns `false` on success. It also reports FAIL for HTTPS and
  Zarinpal when run from wp-cli (no request/TLS context, no merchant id).
- The notification outbox `CREATE TABLE` keeps `PRIMARY KEY (...), UNIQUE KEY ...,
  KEY ...` on one line, which dbDelta cannot parse; it emits a harmless
  "Multiple primary key defined" warning on activation. The table is still created
  with the correct columns.
- Zarinpal (`ZARINPAL_MERCHANT_ID`) and SMS.ir (`SMS_IR_API_KEY`, templates) are
  optional integrations: the storefront runs without them, but payment and OTP SMS
  do not work until real values are provided.

## History worth knowing

Commit `acf9db4` (go-live) inserted mid-token line breaks into
`bamero-production-core.php` and `tests/health_check.php` (e.g. `'__retur`/`n_true'`,
`max_retr`/`ies`). It passes `php -l` (breaks land inside strings) but broke the
outbox table and the health route. Fixed on this branch; `tests/production_gate.sh`
passes again.
