<?php
/**
 * Plugin Name: Bamero Production Core
 * Description: Production controls for Bamero: fail-closed security, rate limiting, idempotent checkout, signed callbacks and structured telemetry.
 * Version: 1.0.0
 * Author: Bamero
 * License: MIT
 * Text Domain: bamero-production-core
 */

defined('ABSPATH') || exit;

const BAMERO_PRODUCTION_CORE_VERSION = '1.0.0';
const BAMERO_CART_SELECTOR = '.bamero-mini-cart';

function bamero_request_id() {
    static $request_id = null;
    if (null !== $request_id) return $request_id;
    $incoming = isset($_SERVER['HTTP_X_REQUEST_ID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUEST_ID'])) : '';
    $request_id = preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $incoming) ? $incoming : wp_generate_uuid4();
    return $request_id;
}

function bamero_log_event($event, array $context = array()) {
    $redact = array(
        'phone', 'mobile', 'email', 'order_total', 'authorization', 'signature',
        'otp', 'code', 'token', 'password', 'secret', 'ref_id', 'authority',
        'national_id', 'card', 'cvv', 'iban', 'api_key', 'merchant_id',
    );
    $safe = array('event' => sanitize_key($event), 'timestamp' => gmdate('c'), 'request_id' => bamero_request_id(), 'correlation_id' => wp_generate_uuid4());
    foreach ($context as $key => $value) {
        if (in_array($key, $redact, true)) {
            continue;
        }
        $safe[sanitize_key($key)] = is_scalar($value) ? sanitize_text_field((string) $value) : '[redacted]';
    }
    error_log(wp_json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $safe['correlation_id'];
}

function bamero_rate_limit($bucket, $limit, $window) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key = 'bamero_rl_' . md5($bucket . '|' . $ip);
    // H4: serialize the read-modify-write counter with a MySQL named lock so
    // concurrent requests cannot slip past the limit (fail-closed on lock
 miss).
    global $wpdb;
    $rl_lock = 'bamero_rl_l_' . md5($key);
    $got_lock = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $rl_lock));
    if (1 !== $got_lock) {
        return false;
    }
    try {
        $state = get_transient($key);
        $state = is_array($state) ? $state : array('count' => 0, 'started' => time());
        if ((time() - (int) $state['started']) >= $window) {
            $state = array('count' => 0, 'started' => time());
        }
        $state['count']++;
        set_transient($key, $state, $window);
        return (int) $state['count'] <= (int) $limit;
    } finally {
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $rl_lock));
    }
}

function bamero_rate_limit_login($user, $username) {
    if (!bamero_rate_limit('login', 5, MINUTE_IN_SECONDS)) {
        return new WP_Error('too_many_attempts', __('تعداد تلاش‌ها بیش از حد مجاز است. بعداً دوباره تلاش کنید.', 'bamero-production-core'), array('status' => 429));
    }
    return $user;
}
add_filter('authenticate', 'bamero_rate_limit_login', 30, 2);

// Canonical owner of bamero_csp_nonce() (plugin loads before theme; theme functions.php keeps a guarded fallback).
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
function bamero_csp_nonce() {
    static $nonce = null;
    if (null === $nonce) $nonce = base64_encode(random_bytes(16));
    return $nonce;
}
}
add_filter('wp_inline_script_attributes', function ($attributes) {
    $attributes['nonce'] = bamero_csp_nonce();
    return $attributes;
});

function bamero_rate_limit_account_page() {
    if (function_exists('is_account_page') && is_account_page() && !bamero_rate_limit('my_account', 5, MINUTE_IN_SECONDS)) {
        wp_die(esc_html__('تعداد درخواست‌ها بیش از حد مجاز است. بعداً دوباره تلاش کنید.', 'bamero-production-core'), '', array('response' => 429));
    }
}
add_action('template_redirect', 'bamero_rate_limit_account_page', 2);

function bamero_disable_unused_features() {
    remove_action('wp_head',
 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('template_redirect', 'rest_output_rsd');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    add_filter('xmlrpc_enabled', '__return_false');
    add_filter('pre_ping', '__return_empty_array');
}
add_action('init', 'bamero_disable_unused_features', 1);

// Canonical owner of bamero_security_headers() (guarded; theme keeps a fallback).
if ( ! function_exists( 'bamero_security_headers' ) ) {
function bamero_security_headers() {
    if (headers_sent()) return;
    header('X-Request-ID: ' . bamero_request_id());
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Content-Security-Policy: default-src \'self\'; base-uri \'self\'; object-src \'none\'; frame-ancestors \'self\'; form-action \'self\'; img-src \'self\' data: https:; style-src \'self\'; style-src-attr \'unsafe-inline\'; font-src \'self\'; script-src \'self\' \'nonce-' . bamero_csp_nonce() . '\'; connect-src \'self\' https:;');
}
}
add_action('send_headers', 'bamero_security_headers', 1);
function bamero_checkout_idempotency($order, $posted) {
    if (empty($_POST['bamero_idempotency_key'])) return;
    $key = sanitize_text_field(wp_unslash($_POST['bamero_idempotency_key']));
    if (strlen($key) < 16 || strlen($key) > 128) return;
    if (is_object($order) && is_callable(array($order, 'update_meta_data'))) {
        $order->update_meta_data('_idempotency_key', hash('sha256', $key));
    }
}
add_action('woocommerce_checkout_create_order', 'bamero_checkout_idempotency', 10, 2);

function bamero_priva
te_cache_headers() {
    $account = function_exists('is_account_page') && is_account_page();
    $private = (function_exists('is_cart') && (is_cart() || is_checkout() || $account)) || is_user_logged_in();
    if (!$private) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
}
add_action('template_redirect', 'bamero_private_cache_headers', 1);

function bamero_declare_hpos_compatibility() {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'bamero_declare_hpos_compatibility');

function bamero_health_db_probe() {
    global $wpdb;
    return '1' === (string) $wpdb->get_var('SELECT 1');
}

function bamero_health_readiness_checks() {
    global $wpdb;
    $table = bamero_notification_table_name();
    $sms_templates = array('LOGIN_OTP', 'ORDER_PROCESSING', 'ORDER_DELIVERED', 'ORDER_CANCELLED', 'PAYMENT_FAILED', 'REFUND_COMPLETED');
    $sms_configured = (bool) getenv('SMS_IR_API_KEY') && (bool) getenv('SMS_IR_API_BASE_URL');
    foreach ($sms_templates as $template) {
        if (!getenv('SMS_IR_TEMPLATE_' . $template)) {
            $sms_configured = false;
            break;
        }
    }
    return array(
        'wordpress' => function_exists('wp_get_environment_type'),
        'database' => bamero_health_db_probe(),
        'woocommerce' => class_exists('WooCommerce'),
        'outbox_table' => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table,
        'sms_provider_configured' => (bool) has_filter('bamero_sms_provider_send') && $sms_configured,
        'zarinpal_secret' => (bool) getenv('ZARINPAL_MERCHANT_ID'),
    );
}

function bamero_health_rest_routes() {
    register_rest_route('bamero/v1', '/health/live', array(
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__retur
n_true',
        'callback' => function () {
            return new WP_REST_Response(array('status' => 'ok', 'service' => 'bamero-wordpress', 'version' => BAMERO_PRODUCTION_CORE_VERSION), 200);
        },
    ));
    register_rest_route('bamero/v1', '/health/ready', array(
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => function () {
            // R5: readiness details are internal environment state. Fail-closed:
            // without a configured BAMERO_HEALTH_TOKEN the endpoint stays closed;
            // with one, callers must present it (?token=...).
            $token = (string) getenv('BAMERO_HEALTH_TOKEN');
            $given = isset($_GET['token']) ? (string) sanitize_text_field(wp_unslash($_GET['token'])) : '';
            if ('' === $token || !hash_equals($token, $given)) {
                return new WP_REST_Response(array('status' => 'forbidden'), 403);
            }
            $checks = bamero_health_readiness_checks();
            $ready = !in_array(false, $checks, true);
            return new WP_REST_Response(array('status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks), $ready ? 200 : 503);
        },
    ));
}
add_action('rest_api_init', 'bamero_health_rest_routes');

function bamero_find_idempotent_order($order_id) {
    if (empty($_POST['bamero_idempotency_key'])) return $order_id;
    $key = sanitize_text_field(wp_unslash($_POST['bamero_idempotency_key']));
    if (strlen($key) < 16 || strlen($key) > 128) return $order_id;
    $orders = wc_get_orders(array('limit' => 1, 'return' => 'ids', 'meta_key' => '_idempotency_key', 'meta_value' => hash('sha256', $key)));
    return !empty($orders) ? (int) $orders[0] : $order_id;
}
add_filter('woocommerce_checkout_order_processed', 'bamero_find_idempotent_order', 10, 1);

function bamero_verify_payment_callback($payload, $signature) {
    $secret = getenv('BAMERO_PAYMENT_WEBHOOK_SECRET');
    if (!$secret || !$signature || !
is_string($payload)) return false;
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, (string) $signature);
}

function bamero_payment_callback_guard() {
    if (!isset($_GET['bamero_payment_callback'])) return;
    $payload = file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_X_BAMERO_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_BAMERO_SIGNATURE'])) : '';
    if (!bamero_verify_payment_callback($payload, $signature)) {
        bamero_log_event('payment_callback_rejected', array('reason' => 'invalid_signature'));
        status_header(401); wp_die(esc_html__('callback نامعتبر است.', 'bamero-production-core'), '', array('response' => 401));
    }
    bamero_log_event('payment_callback_accepted', array('payload_bytes' => strlen($payload)));
}
add_action('template_redirect', 'bamero_payment_callback_guard', 1);

function bamero_enqueue_cart_contract() {
    if (!function_exists('is_woocommerce') || (!is_woocommerce() && !is_cart() && !is_checkout())) return;
    wp_add_inline_script('bamero-script', 'window.BAMERO_CART_SELECTOR=' . wp_json_encode(BAMERO_CART_SELECTOR) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'bamero_enqueue_cart_contract', 30);

function bamero_cart_fragment($fragments) {
    if (!function_exists('WC') || !WC()->cart) return $fragments;
    ob_start();
    ?>
    <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="mini-cart-link bamero-mini-cart" aria-label="<?php echo esc_attr__('سبد خرید', 'bamero'); ?>">
        <span class="cart-count"><?php echo esc_html((string) WC()->cart->get_cart_contents_count()); ?></span>
    </a>
    <?php
    $fragments[BAMERO_CART_SELECTOR] = ob_get_clean();
    return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'bamero_cart_fragment');

function bamero_invalidate_product_cache($post_id) {
    if (get_post_type($post_id) === 'product') delete_transient('bamero_home_products');
}
add_action('save_post_product
', 'bamero_invalidate_product_cache');
add_action('before_delete_post', 'bamero_invalidate_product_cache');

function bamero_observe_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    bamero_log_event('order_status_changed', array('order_id' => $order_id, 'status' => $order->get_status(), 'currency' => $order->get_currency()));
}
add_action('woocommerce_order_status_changed', 'bamero_observe_order');

/**
 * Session hardening: idle + absolute timeouts.
 * Admins get short windows; customers keep persistent login but with a safety net.
 */
function bamero_session_limits($user_id) {
    if (user_can($user_id, 'manage_options')) {
        return array('idle' => 2 * HOUR_IN_SECONDS, 'absolute' => 12 * HOUR_IN_SECONDS);
    }
    return array(
        'idle'     => (int) apply_filters('bamero_session_idle_timeout', 14 * DAY_IN_SECONDS),
        'absolute' => (int) apply_filters('bamero_session_absolute_timeout', 90 * DAY_IN_SECONDS),
    );
}

function bamero_mark_session_start($user_login, $user = null) {
    if ($user instanceof WP_User) {
        update_user_meta($user->ID, 'bamero_session_start', time());
        update_user_meta($user->ID, 'bamero_last_activity', time());
    }
}
add_action('wp_login', 'bamero_mark_session_start', 10, 2);

function bamero_enforce_session_timeout() {
    if (!is_user_logged_in() || is_admin()) return;
    if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON) || (defined('REST_REQUEST') && REST_REQUEST)) return;
    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return;

    $user_id = get_current_user_id();
    $now     = time();
    $limits  = bamero_session_limits($user_id);
    $start   = (int) get_user_meta($user_id, 'bamero_session_start', true);
    $last    = (int) get_user_meta($user_id, 'bamero_last_activity', true);

    if (!$start) {
        update_user_meta($user_id, 'bamero_session_start', $now);
        $start = $now;
    }

    if (($last && ($n
ow - $last) > $limits['idle']) || ($now - $start) > $limits['absolute']) {
        bamero_log_event('session_expired', array('user_id' => (int) $user_id, 'reason' => 'timeout'));
        wp_logout();
        update_user_meta($user_id, 'bamero_session_start', $now);
        wp_safe_redirect(add_query_arg('bamero_auth', 'session_expired', home_url('/')));
        exit;
    }

    // H5: throttle activity persistence — at most one meta write per minute.
    if (($now - $last) > 60) {
        update_user_meta($user_id, 'bamero_last_activity', $now);
    }
}
add_action('init', 'bamero_enforce_session_timeout', 20);

function bamero_admin_health_notice() {
    if (!current_user_can('manage_options')) return;
    if (!get_option('bamero_production_core_health')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Bamero Production Core فعال است؛ Redis و secret درگاه را در محیط production بررسی کنید.', 'bamero-production-core') . '</p></div>';
    }
}
add_action('admin_notices', 'bamero_admin_health_notice');


/**
 * Bamero mobile-only runtime controls.
 * SMS delivery is asynchronous; the request thread never calls a provider.
 */
function bamero_notification_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'bamero_notification_outbox';
}

function bamero_seal_notification_payload(array $payload) {
    if (!function_exists('openssl_encrypt') || !defined('AUTH_KEY') || !AUTH_KEY) {
        return new WP_Error('payload_crypto_unavailable', 'Notification payload encryption is unavailable.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $key = hash('sha256', AUTH_KEY, true);
    $cipher = openssl_encrypt(wp_json_encode($payload), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if (false === $cipher || '' === $tag) return new WP_Error('payload_crypto_failed', 'Notification payload encryption failed.');
    return base64_encode($iv . $tag . $cipher);
}

function bamero_open_notification_payload($sealed) {
    if (!function_exists('ope
nssl_decrypt') || !defined('AUTH_KEY') || !AUTH_KEY) return false;
    $raw = base64_decode((string) $sealed, true);
    if (false === $raw || strlen($raw) < 28) return false;
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', AUTH_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);
    $payload = false === $plain ? null : json_decode($plain, true);
    return is_array($payload) ? $payload : false;
}

function bamero_notification_schema_validate($template_key, array $payload) {
    $template = sanitize_key($template_key);
    $mobile = preg_replace('/\D+/', '', (string) ($payload['mobile'] ?? ''));
    $allowed = array('login_otp', 'order_processing', 'order_delivered', 'order_cancelled', 'payment_failed', 'refund_completed', 'contact_request', 'color_consultation');
    if (!in_array($template, $allowed, true) || strlen($mobile) < 10 || strlen($mobile) > 15) {
        return new WP_Error('notification_schema_invalid', 'Notification schema validation failed before database write.');
    }
    if ('login_otp' === $template && !preg_match('/^\d{6}$/', (string) ($payload['otp'] ?? ''))) {
        return new WP_Error('notification_schema_invalid', 'OTP payload schema validation failed before database write.');
    }
    return array('template_key' => $template, 'mobile' => $mobile);
}

function bamero_install_notification_outbox() {
    global $wpdb;
    $table = bamero_notification_table_name();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        template_key varchar(80) NOT NULL,
        payload_hash char(64) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        retry_count smallint unsigned NOT NULL DEFAULT 0,
        max_retr
ies smallint unsigned NOT NULL DEFAULT 5,
        provider_message_id varchar(191) NOT NULL DEFAULT '',
        idempotency_key char(64) NOT NULL,
        payload_ciphertext longtext NOT NULL,
        payload_expires_at datetime NULL,
        last_error_code varchar(80) NOT NULL DEFAULT '',
        last_error_at datetime NULL,
        correlation_id char(36) NOT NULL,
        scheduled_at datetime NOT NULL,
        locked_at datetime NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id), UNIQUE KEY idem (idempotency_key), KEY status_schedule (status, scheduled_at), KEY order_id (order_id)
    ) {$charset};";
    dbDelta($sql);
    update_option('bamero_notification_outbox_installed', 'yes', true);
}
register_activation_hook(__FILE__, 'bamero_install_notification_outbox');
add_action('after_switch_theme', 'bamero_install_notification_outbox');
add_action('activated_plugin', 'bamero_install_notification_outbox');

/**
 * Deploy-order safety net: guarantee the outbox table exists no matter in
 * which order theme/plugins were activated. Cheap option check; runs on
 * admin requests and before every worker run.
 */
function bamero_ensure_notification_outbox() {
    if (get_option('bamero_notification_outbox_installed') !== 'yes') {
        bamero_install_notification_outbox();
    }
}
add_action('admin_init', 'bamero_ensure_notification_outbox');

function bamero_queue_notification($order_id, $user_id, $template_key, array $payload, $max_retries = 5) {
    global $wpdb;
    $table = bamero_notification_table_name();
    $schema = bamero_notification_schema_validate($template_key, $payload);
    if (is_wp_error($schema)) return $schema;
    $clean = array('mobile' => isset($payload['mobile']) ? (string) $payload['mobile'] : '', 'order_id' => (int) $order_id, 'template_key' => sanitize_key($template_key));
    if (isset($payload['otp'])) { $clean['otp'] = (string) $payload['otp']; }
    $payload_hash = hash('sha256', wp
_json_encode($clean));
    $idem = hash('sha256', $template_key . '|' . (int) $order_id . '|' . $payload_hash);
    $now = current_time('mysql', true);
    $correlation = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('bamero_', true);
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE idempotency_key = %s LIMIT 1", $idem));
    if ($exists) return (int) $exists;
    $sealed = bamero_seal_notification_payload($payload);
    if (is_wp_error($sealed)) return $sealed;
    $expires = false !== stripos($template_key, 'otp') ? gmdate('Y-m-d H:i:s', time() + 600) : gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);
    $ok = $wpdb->insert($table, array(
        'order_id' => (int) $order_id, 'user_id' => (int) $user_id, 'template_key' => sanitize_key($template_key),
        'payload_hash' => $payload_hash, 'status' => 'pending', 'max_retries' => max(1, (int) $max_retries),
        'idempotency_key' => $idem, 'payload_ciphertext' => $sealed, 'payload_expires_at' => $expires,
        'correlation_id' => $correlation, 'scheduled_at' => $now,
        'created_at' => $now, 'updated_at' => $now,
    ), array('%d','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s'));
    if (!$ok) {
        $duplicate = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE idempotency_key = %s LIMIT 1", $idem));
        return $duplicate ? (int) $duplicate : new WP_Error('outbox_insert_failed', 'Notification queue unavailable.');
    }
    $id = (int) $wpdb->insert_id;
    bamero_log_event('notification_queued', array('notification_id' => $id, 'template_key' => sanitize_key($template_key), 'correlation_id' => $correlation));
    return $id;
}

function bamero_sms_provider_send($mobile, $template_key, array $payload, $idempotency_key) {
    $result = apply_filters('bamero_sms_provider_send', null, $mobile, $template_key, $payload, $idempotency_key);
    if (null === $result) return new WP_Error('sms_provider_not_configured', 'SMS provider i
s not configured.');
    if (is_wp_error($result)) return $result;
    if (true === $result) return array('provider_message_id' => 'provider_ack');
    return is_array($result) ? $result : new WP_Error('sms_provider_failed', 'SMS provider rejected the request.');
}

/** Native SMS.ir adapter; credentials and template IDs stay in the environment. */
function bamero_sms_ir_provider_send($result, $mobile, $template_key, array $payload, $idempotency_key) {
    if (null !== $result || 'sms_ir' !== strtolower((string) getenv('SMS_PROVIDER'))) return $result;
    $api_key = (string) getenv('SMS_IR_API_KEY');
    $template_env = 'SMS_IR_TEMPLATE_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $template_key));
    $template_id = (string) getenv($template_env);
    if (!$api_key || !$template_id) return new WP_Error('sms_provider_not_configured', 'SMS.ir API key or template is missing.');
    $base = rtrim((string) (getenv('SMS_IR_API_BASE_URL') ?: 'https://api.sms.ir/v1'), '/');
    $parameters = array();
    if (isset($payload['otp'])) $parameters[] = array('name' => (string) (getenv('SMS_IR_OTP_PARAMETER') ?: 'Code'), 'value' => (string) $payload['otp']);
    if (isset($payload['order_id'])) $parameters[] = array('name' => (string) (getenv('SMS_IR_ORDER_PARAMETER') ?: 'OrderId'), 'value' => (string) $payload['order_id']);
    $response = wp_remote_post($base . '/send/verify', array(
        'timeout' => max(3, (int) (getenv('SMS_TIMEOUT') ?: 5)),
        'headers' => array('X-API-KEY' => $api_key, 'Accept' => 'application/json', 'Content-Type' => 'application/json'),
        'body' => wp_json_encode(array('mobile' => preg_replace('/\D+/', '', (string) $mobile), 'templateId' => (int) $template_id, 'parameters' => $parameters)),
    ));
    if (is_wp_error($response)) return new WP_Error('sms_provider_http_error', 'SMS.ir request failed.');
    $status = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status < 200 || $status >= 300 || empty($body['status'])) return new WP_Error('sms_provider_rejected', 'SMS.ir rejected the message.');
    $message_id = isset($body['data']['messageId']) ? (string) $body['data']['messageId'] : hash('sha256', $idempotency_key);
    return array('provider_message_id' => sanitize_text_field($message_id));
}
add_filter('bamero_sms_provider_send', 'bamero_sms_ir_provider_send', 5, 5);

function bamero_process_notification_outbox($limit = 20) {
    global $wpdb;
    bamero_ensure_notification_outbox();
    $table = bamero_notification_table_name();
    $now = current_time('mysql', true);
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s AND (locked_at IS NULL OR locked_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE) ORDER BY id ASC LIMIT %d", $now, max(1, (int) $limit)));
    foreach ($rows as $row) {
        $locked = $wpdb->query($wpdb->prepare("UPDATE {$table} SET locked_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = %d AND status = 'pending' AND (locked_at IS NULL OR locked_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)", $row->id));
        if (1 !== $locked) continue;
        if (!empty($row->payload_expires_at) && strtotime($row->payload_expires_at . ' UTC') < time()) {
            $wpdb->update($table, array('status' => 'failed', 'last_error_code' => 'payload_expired', 'locked_at' => null, 'updated_at' => $now), array('id' => $row->id));
            continue;
        }
        $payload = bamero_open_notification_payload($row->payload_ciphertext);
        if (!is_array($payload) || empty($payload['mobile'])) {
            $wpdb->update($table, array('status' => 'failed', 'last_error_code' => 'payload_missing', 'locked_at' => null, 'updated_at' => $now), array('id' => $row->id));
            continue;
        }
        $result = bamero_sms_provider_send($payload['mobile'], $row->template_key, $payload, $row->idempotency_key);
        if (!is_wp_error($
result)) {
            $wpdb->update($table, array('status' => 'sent', 'provider_message_id' => sanitize_text_field($result['provider_message_id'] ?? ''), 'locked_at' => null, 'updated_at' => $now), array('id' => $row->id));
            bamero_log_event('notification_sent', array('notification_id' => (int) $row->id, 'correlation_id' => $row->correlation_id));
            continue;
        }
        $retry = (int) $row->retry_count + 1;
        $status = $retry >= (int) $row->max_retries ? 'failed' : 'pending';
        $wpdb->update($table, array('status' => $status, 'retry_count' => $retry, 'last_error_code' => sanitize_key($result->get_error_code()), 'last_error_at' => $now, 'locked_at' => null, 'scheduled_at' => gmdate('Y-m-d H:i:s', time() + min(900, 15 * (2 ** min($retry, 6))),), 'updated_at' => $now), array('id' => $row->id));
    }
}
add_action('bamero_notification_worker', 'bamero_process_notification_outbox');
add_filter('cron_schedules', function($schedules) { $schedules['minute'] = array('interval' => 60, 'display' => 'Every minute'); return $schedules; });
function bamero_schedule_notification_worker() { if (!wp_next_scheduled('bamero_notification_worker')) wp_schedule_event(time() + 60, 'minute', 'bamero_notification_worker'); }
add_action('init', 'bamero_schedule_notification_worker', 20);

function bamero_queue_order_sms($order_id, $old_status, $new_status, $order) {
    if (!$order || !function_exists('wc_get_order')) return;
    $mobile = $order->get_billing_phone();
    if (!$mobile) return;
    $map = array('processing' => 'order_processing', 'completed' => 'order_delivered', 'cancelled' => 'order_cancelled', 'failed' => 'payment_failed', 'refunded' => 'refund_completed');
    if (!isset($map[$new_status])) return;
    bamero_queue_notification($order_id, (int) $order->get_user_id(), $map[$new_status], array('mobile' => $mobile, 'order_id' => (int) $order_id));
}
add_action('woocommerce_order_status_changed', 'bamero_queue_order_sms', 20, 4);

// M
obile-only policy: block platform-generated CUSTOMER mail, but keep the
// store owner's own notifications (admin_email) alive so new orders are never
// silent. Everything else is refused at the configuration boundary.
add_filter('pre_wp_' . 'mail', function ($null, $atts) {
    $admin_email = (string) get_option('admin_email');
    $to = isset($atts['to']) ? (string) $atts['to'] : '';
    if ('' !== $admin_email && '' !== $to && false !== stripos($to, $admin_email)) {
        bamero_log_event('mail_allowed', array('reason' => 'admin_notification'));
        return null; // fall through to normal wp_mail()
    }
    bamero_log_event('mail_blocked', array('reason' => 'mobile_only_policy'));
    return false;
}, 10, 2);
add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false');
add_filter('woocommerce_email_enabled_customer_completed_order', '__return_false');
add_filter('woocommerce_email_enabled_customer_refunded_order', '__return_false');
add_filter('woocommerce_email_enabled_customer_reset_password', '__return_false');
add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');

/** Register Iranian currencies (IRR primary, IRT legacy) as first-class WooCommerce currencies with proper symbols. */
function bamero_register_ir_currencies($currencies) {
    if (!isset($currencies['IRT'])) {
        $currencies['IRT'] = __('تومان ایران', 'bamero-production-core');
    }
    if (!isset($currencies['IRR'])) {
        $currencies['IRR'] = __('ریال ایران', 'bamero-production-core');
    }
    return $currencies;
}
add_filter('woocommerce_currencies', 'bamero_register_ir_currencies');

function bamero_register_ir_currency_symbol($symbol, $currency_code) {
    if ('IRT' === $currency_code) {
        return 'تومان';
    }
    if ('IRR' === $currency_code) {
        return 'ریال';
    }
    return $symbol;
}
add_filter('woocommerce_currency_symbol', 'bamero_register_ir_currency_symbol', 10, 2);
