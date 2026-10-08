#!/usr/bin/env php
<?php
/**
 * Production health check — بامرو
 *
 * محیط‌آگنوستیک: هیچ وابستگی به تعداد محصول/دستهٔ سید ندارد.
 * برای سایت live واقعی:
 *   wp eval-file tests/health_check.php
 *
 * Exit code != 0 یعنی سایت سالم نیست.
 */

defined('ABSPATH') || exit;

$GLOBALS['bamero_health_failures'] = 0;

function bamero_health_check($label, $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) { $GLOBALS['bamero_health_failures']++; }
    return $ok;
}

/* --- WordPress core --- */
bamero_health_check('WordPress loaded', defined('WPINC') && function_exists('wp_get_current_user'));
bamero_health_check('DB connectivity', isset($GLOBALS['wpdb']) && '' === (string) $GLOBALS['wpdb']->last_error);
bamero_health_check('PHP >= 8.3', version_compare(PHP_VERSION, '8.3.0', '>='));
bamero_health_check('HTTPS', is_ssl());

/* --- WooCommerce --- */
bamero_health_check('WooCommerce active', class_exists('WooCommerce'));
if (class_exists('WooCommerce')) {
    bamero_health_check('Store currency set', (string) get_option('woocommerce_currency') !== '');
}

/* --- Theme & plugins --- */
bamero_health_check('Theme bamero active', function_exists('wp_get_theme') && 'bamero' === wp_get_theme()->get('Text Domain'));
$required = array('bamero-production-core', 'bamero-mobile-auth', 'bamero-zarinpal-gateway', 'bamero-woocommerce-setup', 'bamero-custom-plugin');
$active = (array) get_option('active_plugins', array());
foreach ($required as $plugin) {
    bamero_health_check("Plugin {$plugin}", in_array($plugin . '/' . $plugin . '.php', $active, true) || in_array($plugin, array_map('dirname', $active), true));
}

/* --- Payment configuration (config presence، نه تراکنش) --- */
bamero_health_check('Zarinpal merchant id in env', (bool) getenv('ZARINPAL_MERCHANT_ID'));
bamero_health_check('Zarinpal currency sane', in_array(strtoupper((string) (getenv('ZARINPAL_CURRENCY') ?: 'IRR')), array('IRR', 'IRT'), true));

/* --- Cron & uploads --- */
bamero_health_check('WP-Cron enabled', !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON);
// M4 remediation: the old check compared against a non-existent option ('disabled_wp_cron')
// and passed even when DISABLE_WP_CRON was set — false assurance. Now fail-closed:
// if cron is disabled, system crontab must be verified manually (documented NOT VERIFIED).
$upload = wp_upload_dir();
bamero_health_check('Uploads writable', isset($upload['error']) && '' === $upload['error']);

/* --- Critical pages --- */
foreach (array('privacy' => 'حریم خصوصی', 'terms' => 'شرایط استفاده') as $slug => $label) {
    bamero_health_check("Page {$label} (/{$slug}/)", (bool) get_page_by_path($slug));
}

/* --- Security posture --- */
bamero_health_check('Debug off in production', !((bool) WP_DEBUG) || 'production' !== wp_get_environment_type());
bamero_health_check('No admin user', !get_user_by('login', 'admin'));

/* --- Database: notification outbox table --- */
global $wpdb;
$table = $wpdb->prefix . 'bamero_notification_outbox';
bamero_health_check("Outbox table ({$table})", $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)));

if (!defined('BAMERO_SMOKE_RUNNER')) {
    if ($GLOBALS['bamero_health_failures'] > 0) {
        echo "\nنتیجهٔ health: " . (int) $GLOBALS['bamero_health_failures'] . " مورد ناموفق.\n";
        exit(1);
    }
    echo "\nنتیجهٔ health: سایت سالم است.\n";
    exit(0);
}
