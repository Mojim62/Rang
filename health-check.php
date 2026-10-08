<?php
/**
 * Bamero Go-Live Health Check
 * Runtime monitoring endpoint for production readiness validation
 * 
 * This file provides comprehensive health checks for the Bamero e-commerce store
 * to ensure all systems are operational before and after Go-Live.
 */

defined('ABSPATH') || exit;

/**
 * Health Check Configuration
 */
const BAMERO_HEALTH_CHECK_VERSION = '1.0.0';
const BAMERO_HEALTH_CHECK_TOKEN = 'BAMERO_HEALTH_TOKEN';

/**
 * Main health check endpoint
 * Access: /health-check.php?token=YOUR_TOKEN
 */
function bamero_health_check() {
    header('Content-Type: application/json');
    
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    $env_token = (string) getenv(BAMERO_HEALTH_CHECK_TOKEN);
    
    // Allow health check without token in development
    $is_development = in_array(wp_get_environment_type(), array('development', 'staging', 'local'), true);
    
    if (empty($env_token) && !$is_development) {
        http_response_code(403);
        echo wp_json_encode(array(
            'status' => 'error',
            'message' => 'Health check token not configured',
            'timestamp' => gmdate('c'),
        ));
        exit;
    }
    
    if (!hash_equals($env_token, $token) && !$is_development) {
        http_response_code(403);
        echo wp_json_encode(array(
            'status' => 'error',
            'message' => 'Invalid health check token',
            'timestamp' => gmdate('c'),
        ));
        exit;
    }
    
    $checks = bamero_run_health_checks();
    $all_passed = !in_array(false, array_column($checks, 'passed'), true);
    
    $response = array(
        'status' => $all_passed ? 'healthy' : 'degraded',
        'version' => BAMERO_HEALTH_CHECK_VERSION,
        'timestamp' => gmdate('c'),
        'checks' => $checks,
        'summary' => bamero_get_health_summary($checks),
    );
    
    http_response_code($all_passed ? 200 : 503);
    echo wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Run all health checks
 */
function bamero_run_health_checks() {
    $checks = array();
    
    // Core system checks
    $checks[] = bamero_check_php_version();
    $checks[] = bamero_check_php_extensions();
    $checks[] = bamero_check_database();
    $checks[] = bamero_check_wordpress();
    $checks[] = bamero_check_woocommerce();
    
    // Plugin checks
    $checks[] = bamero_check_bamero_plugins();
    
    // Theme checks
    $checks[] = bamero_check_bamero_theme();
    
    // Configuration checks
    $checks[] = bamero_check_currency_settings();
    $checks[] = bamero_check_country_settings();
    $checks[] = bamero_check_payment_gateways();
    $checks[] = bamero_check_shipping_methods();
    
    // Security checks
    $checks[] = bamero_check_security_settings();
    
    // HPOS checks
    $checks[] = bamero_check_hpos_compatibility();
    
    // Database tables
    $checks[] = bamero_check_database_tables();
    
    // Required pages
    $checks[] = bamero_check_required_pages();
    
    // Environment checks
    $checks[] = bamero_check_environment_variables();
    
    return $checks;
}

/**
 * Get health summary
 */
function bamero_get_health_summary($checks) {
    $passed = array_filter($checks, function($check) { return $check['passed']; });
    $failed = array_filter($checks, function($check) { return !$check['passed']; });
    
    return array(
        'total_checks' => count($checks),
        'passed' => count($passed),
        'failed' => count($failed),
        'pass_rate' => round((count($passed) / max(1, count($checks))) * 100, 2) . '%',
    );
}

/**
 * PHP Version Check
 */
function bamero_check_php_version() {
    $required = '8.0';
    $current = phpversion();
    $passed = version_compare($current, $required, '>=');
    
    return array(
        'name' => 'PHP Version',
        'check' => 'php_version',
        'passed' => $passed,
        'required' => $required,
        'current' => $current,
        'message' => $passed ? 'PHP version meets requirement' : 'PHP version too old',
        'severity' => $passed ? 'info' : 'critical',
    );
}

/**
 * PHP Extensions Check
 */
function bamero_check_php_extensions() {
    $required_extensions = array(
        'mysql', 'mysqli', 'pdo_mysql', 'curl', 'json', 'mbstring',
        'openssl', 'gd', 'zip', 'dom', 'xml', 'fileinfo',
    );
    
    $missing = array();
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    $passed = empty($missing);
    
    return array(
        'name' => 'PHP Extensions',
        'check' => 'php_extensions',
        'passed' => $passed,
        'required' => implode(', ', $required_extensions),
        'current' => $passed ? 'All extensions loaded' : 'Missing: ' . implode(', ', $missing),
        'message' => $passed ? 'All required PHP extensions are loaded' : 'Some PHP extensions are missing',
        'severity' => $passed ? 'info' : 'critical',
    );
}

/**
 * Database Check
 */
function bamero_check_database() {
    global $wpdb;
    
    $connected = true;
    $error = '';
    
    try {
        $wpdb->get_var('SELECT 1');
    } catch (Exception $e) {
        $connected = false;
        $error = $e->getMessage();
    }
    
    return array(
        'name' => 'Database Connection',
        'check' => 'database_connection',
        'passed' => $connected,
        'current' => $connected ? 'Connected' : 'Disconnected',
        'message' => $connected ? 'Database connection is healthy' : 'Database connection failed: ' . $error,
        'severity' => $connected ? 'info' : 'critical',
    );
}

/**
 * WordPress Check
 */
function bamero_check_wordpress() {
    $required_version = '6.0';
    $current_version = get_bloginfo('version');
    $passed = version_compare($current_version, $required_version, '>=');
    
    return array(
        'name' => 'WordPress',
        'check' => 'wordpress',
        'passed' => $passed,
        'required' => $required_version,
        'current' => $current_version,
        'message' => $passed ? 'WordPress version meets requirement' : 'WordPress version too old',
        'severity' => $passed ? 'info' : 'critical',
    );
}

/**
 * WooCommerce Check
 */
function bamero_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        return array(
            'name' => 'WooCommerce',
            'check' => 'woocommerce',
            'passed' => false,
            'current' => 'Not installed',
            'message' => 'WooCommerce is not installed',
            'severity' => 'critical',
        );
    }
    
    $required_version = '7.0';
    $current_version = WC()->version;
    $passed = version_compare($current_version, $required_version, '>=');
    
    return array(
        'name' => 'WooCommerce',
        'check' => 'woocommerce',
        'passed' => $passed,
        'required' => $required_version,
        'current' => $current_version,
        'message' => $passed ? 'WooCommerce version meets requirement' : 'WooCommerce version too old',
        'severity' => $passed ? 'info' : 'warning',
    );
}

/**
 * Bamero Plugins Check
 */
function bamero_check_bamero_plugins() {
    $bamero_plugins = array(
        'bamero-mobile-auth/bamero-mobile-auth.php',
        'bamero-production-core/bamero-production-core.php',
        'bamero-zarinpal-gateway/bamero-zarinpal-gateway.php',
        'bamero-sms-provider/bamero-sms-provider.php',
        'bamero-notifications/bamero-notifications.php',
        'bamero-iran-shipping/bamero-iran-shipping.php',
    );
    
    $active_plugins = get_option('active_plugins', array());
    $missing = array();
    $inactive = array();
    
    foreach ($bamero_plugins as $plugin) {
        $plugin_path = 'bamero-' . basename($plugin, '.php') . '/' . basename($plugin);
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_path)) {
            $missing[] = basename($plugin, '.php');
        } elseif (!in_array($plugin_path, $active_plugins)) {
            $inactive[] = basename($plugin, '.php');
        }
    }
    
    $passed = empty($missing);
    
    return array(
        'name' => 'Bamero Plugins',
        'check' => 'bamero_plugins',
        'passed' => $passed,
        'required' => implode(', ', array_map(function($p) { return basename($p, '.php'); }, $bamero_plugins)),
        'current' => empty($missing) ? 'All plugins present' : 'Missing: ' . implode(', ', $missing),
        'message' => $passed ? 'All Bamero plugins are present' : 'Some Bamero plugins are missing',
        'severity' => $passed ? 'info' : 'critical',
    );
}

/**
 * Bamero Theme Check
 */
function bamero_check_bamero_theme() {
    $current_theme = wp_get_theme();
    $is_bamero = $current_theme->get('TextDomain') === 'bamero' || strpos($current_theme->get_stylesheet(), 'bamero') !== false;
    
    return array(
        'name' => 'Bamero Theme',
        'check' => 'bamero_theme',
        'passed' => $is_bamero,
        'required' => 'bamero',
        'current' => $current_theme->get('Name'),
        'message' => $is_bamero ? 'Bamero theme is active' : 'Bamero theme is not active',
        'severity' => $is_bamero ? 'info' : 'warning',
    );
}

/**
 * Currency Settings Check
 */
function bamero_check_currency_settings() {
    $currency = get_woocommerce_currency();
    $passed = in_array($currency, array('IRR', 'IRT'), true);
    
    return array(
        'name' => 'Currency Settings',
        'check' => 'currency_settings',
        'passed' => $passed,
        'required' => 'IRR or IRT',
        'current' => $currency,
        'message' => $passed ? 'Currency is set to IRR/IRT' : 'Currency is not set to IRR/IRT',
        'severity' => $passed ? 'info' : 'critical',
    );
}

/**
 * Country Settings Check
 */
function bamero_check_country_settings() {
    $default_country = WC()->countries->get_base_country();
    $default_state = WC()->countries->get_base_state();
    
    $passed = $default_country === 'IR' && ($default_state === 'IS' || $default_state === 'Isfahan');
    
    return array(
        'name' => 'Country/State Settings',
        'check' => 'country_settings',
        'passed' => $passed,
        'required' => 'IR/Isfahan',
        'current' => $default_country . '/' . $default_state,
        'message' => $passed ? 'Country and state are correctly configured' : 'Country or state not correctly configured',
        'severity' => $passed ? 'info' : 'warning',
    );
}

/**
 * Payment Gateways Check
 */
function bamero_check_payment_gateways() {
    $gateways = WC()->payment_gateways->payment_gateways();
    $zarinpal_enabled = isset($gateways['bamero_zarinpal']) && $gateways['bamero_zarinpal']->is_available();
    
    return array(
        'name' => 'Payment Gateways',
        'check' => 'payment_gateways',
        'passed' => $zarinpal_enabled,
        'required' => 'Zarinpal enabled',
        'current' => $zarinpal_enabled ? 'Zarinpal enabled' : 'Zarinpal not enabled',
        'message' => $zarinpal_enabled ? 'Zarinpal payment gateway is enabled' : 'Zarinpal payment gateway is not enabled',
        'severity' => $zarinpal_enabled ? 'info' : 'critical',
    );
}

/**
 * Shipping Methods Check
 */
function bamero_check_shipping_methods() {
    $shipping_zones = WC_Shipping_Zones::get_zones();
    $methods = array();
    
    foreach ($shipping_zones as $zone) {
        foreach ($zone['shipping_methods'] as $method) {
            $methods[] = $method->id;
        }
    }
    
    $has_post = in_array('post', $methods, true);
    $has_tipax = in_array('tipax', $methods, true);
    $passed = $has_post && $has_tipax;
    
    return array(
        'name' => 'Shipping Methods',
        'check' => 'shipping_methods',
        'passed' => $passed,
        'required' => 'Post and Tipax',
        'current' => implode(', ', $methods),
        'message' => $passed ? 'Both Post and Tipax shipping methods are configured' : 'Missing shipping methods',
        'severity' => $passed ? 'info' : 'warning',
    );
}

/**
 * Security Settings Check
 */
function bamero_check_security_settings() {
    $checks = array(
        'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG === false,
        'DISALLOW_FILE_MODS' => defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS === true,
        'FORCE_SSL_ADMIN' => defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN === true,
        'is_ssl' => is_ssl(),
    );
    
    $passed = !in_array(false, $checks, true);
    
    return array(
        'name' => 'Security Settings',
        'check' => 'security_settings',
        'passed' => $passed,
        'required' => 'All security settings enabled',
        'current' => json_encode($checks),
        'message' => $passed ? 'All security settings are correctly configured' : 'Some security settings are not configured',
        'severity' => $passed ? 'info' : 'critical',
    );
}

/**
 * HPOS Compatibility Check
 */
function bamero_check_hpos_compatibility() {
    $hpos_enabled = class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil') && \Automattic\WooCommerce\Utilities\FeaturesUtil::is_feature_enabled('custom_order_tables');
    
    return array(
        'name' => 'HPOS Compatibility',
        'check' => 'hpos_compatibility',
        'passed' => $hpos_enabled,
        'required' => 'HPOS enabled',
        'current' => $hpos_enabled ? 'Enabled' : 'Disabled',
        'message' => $hpos_enabled ? 'HPOS is enabled' : 'HPOS is not enabled',
        'severity' => $hpos_enabled ? 'info' : 'warning',
    );
}

/**
 * Database Tables Check
 */
function bamero_check_database_tables() {
    global $wpdb;
    
    $required_tables = array(
        $wpdb->prefix . 'bamero_notification_outbox',
    );
    
    $missing_tables = array();
    foreach ($required_tables as $table) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if (!$exists) {
            $missing_tables[] = $table;
        }
    }
    
    $passed = empty($missing_tables);
    
    return array(
        'name' => 'Database Tables',
        'check' => 'database_tables',
        'passed' => $passed,
        'required' => implode(', ', $required_tables),
        'current' => $passed ? 'All tables exist' : 'Missing: ' . implode(', ', $missing_tables),
        'message' => $passed ? 'All required database tables exist' : 'Some database tables are missing',
        'severity' => $passed ? 'info' : 'warning',
    );
}

/**
 * Required Pages Check
 */
function bamero_check_required_pages() {
    $required_pages = array(
        'shop' => wc_get_page_id('shop'),
        'cart' => wc_get_page_id('cart'),
        'checkout' => wc_get_page_id('checkout'),
        'myaccount' => wc_get_page_id('myaccount'),
    );
    
    $bamero_pages = array(
        'color-consultation' => get_page_by_path('color-consultation'),
        'about-us' => get_page_by_path('about-us'),
        'contact-us' => get_page_by_path('contact-us'),
        'privacy-policy' => get_page_by_path('privacy-policy'),
        'terms-of-service' => get_page_by_path('terms-of-service'),
    );
    
    $missing = array();
    foreach ($required_pages as $page => $id) {
        if (!$id) {
            $missing[] = $page;
        }
    }
    
    foreach ($bamero_pages as $page => $post) {
        if (!$post) {
            $missing[] = $page;
        }
    }
    
    $passed = empty($missing);
    
    return array(
        'name' => 'Required Pages',
        'check' => 'required_pages',
        'passed' => $passed,
        'required' => implode(', ', array_keys($required_pages)) . ' + Bamero pages',
        'current' => $passed ? 'All pages exist' : 'Missing: ' . implode(', ', $missing),
        'message' => $passed ? 'All required pages exist' : 'Some required pages are missing',
        'severity' => $passed ? 'info' : 'warning',
    );
}

/**
 * Environment Variables Check
 */
function bamero_check_environment_variables() {
    $required_vars = array(
        'ZARINPAL_MERCHANT_ID',
        'SMS_IR_API_KEY',
        'SMS_IR_API_BASE_URL',
        'BAMERO_ENVIRONMENT',
    );
    
    $missing = array();
    foreach ($required_vars as $var) {
        if (!getenv($var)) {
            $missing[] = $var;
        }
    }
    
    // In staging, SMS and payment can be disabled
    $environment = getenv('BAMERO_ENVIRONMENT');
    if ($environment === 'staging') {
        // Remove SMS and Zarinpal from missing if staging flags are set
        if (getenv('BAMERO_ENABLE_SMS_IN_STAGING') === 'true') {
            $missing = array_filter($missing, function($v) { return $v !== 'SMS_IR_API_KEY' && $v !== 'SMS_IR_API_BASE_URL'; });
        }
        if (getenv('BAMERO_ENABLE_PAYMENT_IN_STAGING') === 'true') {
            $missing = array_filter($missing, function($v) { return $v !== 'ZARINPAL_MERCHANT_ID'; });
        }
    }
    
    $passed = empty($missing);
    
    return array(
        'name' => 'Environment Variables',
        'check' => 'environment_variables',
        'passed' => $passed,
        'required' => implode(', ', $required_vars),
        'current' => $passed ? 'All variables configured' : 'Missing: ' . implode(', ', $missing),
        'message' => $passed ? 'All required environment variables are configured' : 'Some environment variables are missing',
        'severity' => $passed ? 'info' : ($environment === 'production' ? 'critical' : 'warning'),
    );
}

/**
 * Run health check when file is accessed directly
 */
if (php_sapi_name() === 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
    bamero_health_check();
}
