<?php
/**
 * Plugin Name: Bamero Notifications
 * Description: Notification system for Bamero e-commerce (SMS, Email queue)
 * Version: 1.0.0
 * Author: Bamero
 * License: MIT
 * Text Domain: bamero-notifications
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('BAMERO_NOTIFICATIONS_VERSION', '1.0.0');

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Notification table name
 */
function bamero_notification_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'bamero_notification_outbox';
}

/**
 * Install notification outbox table
 */
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
        max_retries smallint unsigned NOT NULL DEFAULT 5,
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
        PRIMARY KEY (id),
        UNIQUE KEY idem (idempotency_key),
        KEY status_schedule (status, scheduled_at),
        KEY order_id (order_id)
    ) {$charset};";

    dbDelta($sql);
    update_option('bamero_notification_outbox_installed', 'yes', true);
}

register_activation_hook(__FILE__, 'bamero_install_notification_outbox');

/**
 * Ensure notification outbox table exists
 */
function bamero_ensure_notification_outbox() {
    if (get_option('bamero_notification_outbox_installed') !== 'yes') {
        bamero_install_notification_outbox();
    }
}

add_action('admin_init', 'bamero_ensure_notification_outbox');

/**
 * Seal notification payload with encryption
 */
function bamero_seal_notification_payload(array $payload) {
    if (!function_exists('openssl_encrypt') || !defined('AUTH_KEY') || !AUTH_KEY) {
        return new WP_Error('payload_crypto_unavailable', 'Notification payload encryption is unavailable.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $key = hash('sha256', AUTH_KEY, true);
    $cipher = openssl_encrypt(wp_json_encode($payload), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if (false === $cipher || '' === $tag) {
        return new WP_Error('payload_crypto_failed', 'Notification payload encryption failed.');
    }

    return base64_encode($iv . $tag . $cipher);
}

/**
 * Open notification payload with decryption
 */
function bamero_open_notification_payload($sealed) {
    if (!function_exists('openssl_decrypt') || !defined('AUTH_KEY') || !AUTH_KEY) {
        return false;
    }

    $raw = base64_decode((string) $sealed, true);
    if (false === $raw || strlen($raw) < 28) {
        return false;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', AUTH_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);

    if (false === $plain) {
        return false;
    }

    $payload = json_decode($plain, true);
    return is_array($payload) ? $payload : false;
}

/**
 * Validate notification schema
 */
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

/**
 * Queue notification for delivery
 */
function bamero_queue_notification($order_id, $user_id, $template_key, array $payload, $max_retries = 5) {
    global $wpdb;
    $table = bamero_notification_table_name();

    $schema = bamero_notification_schema_validate($template_key, $payload);
    if (is_wp_error($schema)) {
        return $schema;
    }

    $clean = array('mobile' => isset($payload['mobile']) ? (string) $payload['mobile'] : '', 'order_id' => (int) $order_id, 'template_key' => sanitize_key($template_key));
    if (isset($payload['otp'])) {
        $clean['otp'] = (string) $payload['otp'];
    }

    $payload_hash = hash('sha256', wp_json_encode($clean));
    $idem = hash('sha256', $template_key . '|' . (int) $order_id . '|' . $payload_hash);
    $now = current_time('mysql', true);
    $correlation = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('bamero_', true);

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE idempotency_key = %s LIMIT 1", $idem));
    if ($exists) {
        return (int) $exists;
    }

    $sealed = bamero_seal_notification_payload($payload);
    if (is_wp_error($sealed)) {
        return $sealed;
    }

    $expires = false !== stripos($template_key, 'otp') ? gmdate('Y-m-d H:i:s', time() + 600) : gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);

    $ok = $wpdb->insert($table, array(
        'order_id' => (int) $order_id,
        'user_id' => (int) $user_id,
        'template_key' => sanitize_key($template_key),
        'payload_hash' => $payload_hash,
        'status' => 'pending',
        'max_retries' => max(1, (int) $max_retries),
        'idempotency_key' => $idem,
        'payload_ciphertext' => $sealed,
        'payload_expires_at' => $expires,
        'correlation_id' => $correlation,
        'scheduled_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ), array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));

    if (!$ok) {
        $duplicate = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE idempotency_key = %s LIMIT 1", $idem));
        return $duplicate ? (int) $duplicate : new WP_Error('outbox_insert_failed', 'Notification queue unavailable.');
    }

    $id = (int) $wpdb->insert_id;

    if (function_exists('bamero_log_event')) {
        bamero_log_event('notification_queued', array('notification_id' => $id, 'template_key' => sanitize_key($template_key), 'correlation_id' => $correlation));
    }

    return $id;
}

/**
 * SMS Provider Send function
 */
function bamero_sms_provider_send($mobile, $template_key, array $payload, $idempotency_key) {
    $result = apply_filters('bamero_sms_provider_send', null, $mobile, $template_key, $payload, $idempotency_key);

    if (null === $result) {
        return new WP_Error('sms_provider_not_configured', 'SMS provider is not configured.');
    }

    if (is_wp_error($result)) {
        return $result;
    }

    if (true === $result) {
        return array('provider_message_id' => 'provider_ack');
    }

    return is_array($result) ? $result : new WP_Error('sms_provider_failed', 'SMS provider rejected the request.');
}

/**
 * Process notification outbox
 */
function bamero_process_notification_outbox($limit = 20) {
    global $wpdb;
    bamero_ensure_notification_outbox();
    $table = bamero_notification_table_name();
    $now = current_time('mysql', true);

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s AND (locked_at IS NULL OR locked_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE) ORDER BY id ASC LIMIT %d",
        $now,
        max(1, (int) $limit)
    ));

    foreach ($rows as $row) {
        $locked = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET locked_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = %d AND status = 'pending' AND (locked_at IS NULL OR locked_at < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)",
            $row->id
        ));

        if (1 !== $locked) {
            continue;
        }

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

        if (!is_wp_error($result)) {
            $wpdb->update($table, array(
                'status' => 'sent',
                'provider_message_id' => sanitize_text_field($result['provider_message_id'] ?? ''),
                'locked_at' => null,
                'updated_at' => $now
            ), array('id' => $row->id));

            if (function_exists('bamero_log_event')) {
                bamero_log_event('notification_sent', array('notification_id' => (int) $row->id, 'correlation_id' => $row->correlation_id));
            }
            continue;
        }

        $retry = (int) $row->retry_count + 1;
        $status = $retry >= (int) $row->max_retries ? 'failed' : 'pending';

        $wpdb->update($table, array(
            'status' => $status,
            'retry_count' => $retry,
            'last_error_code' => sanitize_key($result->get_error_code()),
            'last_error_at' => $now,
            'locked_at' => null,
            'scheduled_at' => gmdate('Y-m-d H:i:s', time() + min(900, 15 * (2 ** min($retry, 6)))),
            'updated_at' => $now
        ), array('id' => $row->id));
    }
}

add_action('bamero_notification_worker', 'bamero_process_notification_outbox');

/**
 * Schedule notification worker
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['minute'] = array('interval' => 60, 'display' => 'Every minute');
    return $schedules;
});

function bamero_schedule_notification_worker() {
    if (!wp_next_scheduled('bamero_notification_worker')) {
        wp_schedule_event(time() + 60, 'minute', 'bamero_notification_worker');
    }
}

add_action('init', 'bamero_schedule_notification_worker', 20);

/**
 * Queue order SMS notifications
 */
function bamero_queue_order_sms($order_id, $old_status, $new_status, $order) {
    if (!$order || !function_exists('wc_get_order')) {
        return;
    }

    $mobile = $order->get_billing_phone();
    if (!$mobile) {
        return;
    }

    $map = array(
        'processing' => 'order_processing',
        'completed' => 'order_delivered',
        'cancelled' => 'order_cancelled',
        'failed' => 'payment_failed',
        'refunded' => 'refund_completed'
    );

    if (!isset($map[$new_status])) {
        return;
    }

    bamero_queue_notification($order_id, (int) $order->get_user_id(), $map[$new_status], array(
        'mobile' => $mobile,
        'order_id' => (int) $order_id
    ));
}

add_action('woocommerce_order_status_changed', 'bamero_queue_order_sms', 20, 4);
