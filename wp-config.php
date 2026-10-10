<?php
/**
 * Bamero production wp-config — fail-closed secrets.
 *
 * Secrets are resolved with this precedence:
 *   1. Real environment variables (hosting panel / PHP-FPM pool / .htaccess SetEnv)
 *   2. A .env file OUTSIDE the web root (recommended): ../bamero.env or ../.env
 *   3. A .env file INSIDE the web root (blocked from HTTP by .htaccess)
 *
 * Copy .env.example to .env, fill in every value, then upload it. NEVER commit a
 * real .env. The site refuses to boot (HTTP 500) if a required value is missing
 * or is still a placeholder — this prevents an insecure half-configured launch.
 */

declare(strict_types=1);

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

/**
 * Minimal .env loader so the site boots on shared PHP hosting that cannot set
 * real environment variables. Existing env vars always take precedence.
 */
function bamero_load_env_file(): void {
    $candidates = array();

    if (defined('BAMERO_ENV_FILE')) {
        $candidates[] = (string) BAMERO_ENV_FILE;
    }

    // Preferred: one level above the web root (not reachable over HTTP).
    $candidates[] = dirname(ABSPATH) . '/bamero.env';
    $candidates[] = dirname(ABSPATH) . '/.env';

    // Fallback: inside the web root (protected by .htaccess FilesMatch rule).
    $candidates[] = ABSPATH . '.env';

    foreach ($candidates as $file) {
        if (!is_string($file) || $file === '' || !is_readable($file)) {
            continue;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if ($key === '') {

                continue;
            }

            // Strip a single pair of surrounding quotes.
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last  = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($key) === false || getenv($key) === '') {
                if (function_exists('putenv')) {
                    @putenv($key . '=' . $value);
                }
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }

        return; // use the first readable file only
    }
}

bamero_load_env_file();

/**
 * Require non-empty env; reject known placeholders. Uses die() before WP loads.
 */
function bamero_require_env(string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        // Fallbacks for hosts where putenv()/getenv() is restricted.
        $value = isset($_ENV[$key]) ? $_ENV[$key] : (isset($_SERVER[$key]) ? $_SERVER[$key] : '');
    }
    if ($value === false || $value === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        die('Configuration error: missing required environment variable: ' . $key);
    }
    if (stripos($value, '' . 'put-your-' . 'unique-phrase') !== false
        || stripos($value, 'putyour') !== false
        || in_array(strtolower($value), array('changeme', 'secret', 'password', 'null', 'root', ''), true)
    ) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        die('Configuration error: rejected placeholder/insecure value for: ' . $key);
    }
    return $value;
}

define('DB_NAME', bamero_require_env('DB_NAME'));
define('DB_USER', bamero_require_env('DB_USER'));
define('DB_PASSWORD', bamero_require_env('DB_PASSWO
RD'));
define('DB_HOST', bamero_require_env('DB_HOST'));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('AUTH_KEY',         bamero_require_env('AUTH_KEY'));
define('SECURE_AUTH_KEY',  bamero_require_env('SECURE_AUTH_KEY'));
define('LOGGED_IN_KEY',    bamero_require_env('LOGGED_IN_KEY'));
define('NONCE_KEY',        bamero_require_env('NONCE_KEY'));
define('AUTH_SALT',        bamero_require_env('AUTH_SALT'));
define('SECURE_AUTH_SALT', bamero_require_env('SECURE_AUTH_SALT'));
define('LOGGED_IN_SALT',   bamero_require_env('LOGGED_IN_SALT'));
define('NONCE_SALT',       bamero_require_env('NONCE_SALT'));

$table_prefix = (getenv('TABLE_PREFIX') !== false && getenv('TABLE_PREFIX') !== '')
    ? getenv('TABLE_PREFIX')
    : 'wp_bamero_';

/**
 * Optional: pin the site domain via env so the owner can set it without editing
 * the database. Leave unset to use the values stored in wp_options.
 */
$bamero_home = getenv('WP_HOME');
if ($bamero_home !== false && $bamero_home !== '') {
    define('WP_HOME', rtrim($bamero_home, '/'));
}
$bamero_siteurl = getenv('WP_SITEURL');
if ($bamero_siteurl !== false && $bamero_siteurl !== '') {
    define('WP_SITEURL', rtrim($bamero_siteurl, '/'));
}

define('WP_DEBUG', filter_var(getenv('WP_DEBUG') !== false ? getenv('WP_DEBUG') : '0', FILTER_VALIDATE_BOOLEAN));
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', filter_var(getenv('WP_DEBUG_LOG') !== false ? getenv('WP_DEBUG_LOG') : '0', FILTER_VALIDATE_BOOLEAN));

define('DISALLOW_FILE_EDIT', true);
$dfm = getenv('DISALLOW_FILE_MODS');
define('DISALLOW_FILE_MODS', filter_var($dfm !== false ? $dfm : '1', FILTER_VALIDATE_BOOLEAN));
define('FORCE_SSL_ADMIN', true);
$bamero_wp_cache = getenv('WP_CACHE');
define('WP_CACHE', filter_var($bamero_wp_cache !== false ? $bamero_wp_cache : '0', FILTER_VALIDATE_BOOLEAN));
define('CONCATENATE_SCRIPTS', false);

/**
 * Optional: disable WordPress in-request cron when the host schedules a real
 * cron job (recommended for production; see GO_LIVE_RUNBOOK_FA.md, section 3).
 * Leave unset or 0 to keep the WordPress default behavior.
 */
$bamero_disable_cron = getenv('DISABLE_WP_CRON');
define('DISABLE_WP_CRON', filter_var($bamero_disable_cron !== false ? $bamero_disable_cron : '0', FILTER_VALIDATE_BOOLEAN));

define('EMPTY_TRASH_DAYS', 14);
define('WP_POST_REVISIONS', 5);
define('AUTOSAVE_INTERVAL', 120);
define('WP_MEMORY_LIM
IT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

$env_type = getenv('WP_ENVIRONMENT_TYPE');
if ($env_type !== false && $env_type !== '') {
    define('WP_ENVIRONMENT_TYPE', $env_type);
}

require_once ABSPATH . 'wp-settings.php';