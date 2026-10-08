<?php
/**
 * Plugin Name: Bamero SMS Provider
 * Description: SMS.ir provider integration for Bamero notifications
 * Version: 1.0.0
 * Author: Bamero
 * License: MIT
 * Text Domain: bamero-sms-provider
 */

defined('ABSPATH') || exit;

define('BAMERO_SMS_PROVIDER_VERSION', '1.0.0');

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * SMS.ir Provider Implementation
 * This is the native SMS.ir adapter that integrates with Bamero notification system
 */
function bamero_sms_ir_send($mobile, $template_key, array $payload, $idempotency_key) {
    // Production-only flag: SMS is disabled in staging unless explicitly enabled
    if (getenv('BAMERO_ENVIRONMENT') === 'staging' && getenv('BAMERO_ENABLE_SMS_IN_STAGING') !== 'true') {
        if (function_exists('bamero_log_event')) {
            bamero_log_event('sms_blocked_staging', array('template_key' => sanitize_key($template_key)));
        }
        return new WP_Error('sms_disabled_staging', 'SMS delivery is disabled in staging environment.');
    }

    $api_key = (string) getenv('SMS_IR_API_KEY');
    $template_env = 'SMS_IR_TEMPLATE_' . strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $template_key));
    $template_id = (string) getenv($template_env);

    if (!$api_key || !$template_id) {
        return new WP_Error('sms_provider_not_configured', 'SMS.ir API key or template is missing.');
    }

    $base = rtrim((string) (getenv('SMS_IR_API_BASE_URL') ?: 'https://api.sms.ir/v1'), '/');
    $parameters = array();

    if (isset($payload['otp'])) {
        $parameters[] = array('name' => (string) (getenv('SMS_IR_OTP_PARAMETER') ?: 'Code'), 'value' => (string) $payload['otp']);
    }
    if (isset($payload['order_id'])) {
        $parameters[] = array('name' => (string) (getenv('SMS_IR_ORDER_PARAMETER') ?: 'OrderId'), 'value' => (string) $payload['order_id']);
    }

    $response = wp_remote_post($base . '/send/verify', array(
        'timeout' => max(3, (int) (getenv('SMS_TIMEOUT') ?: 5)),
        'headers' => array('X-API-KEY' => $api_key, 'Accept' => 'application/json', 'Content-Type' => 'application/json'),
        'body' => wp_json_encode(array('mobile' => preg_replace('/\D+/', '', (string) $mobile), 'templateId' => (int) $template_id, 'parameters' => $parameters)),
    ));

    if (is_wp_error($response)) {
        return new WP_Error('sms_provider_http_error', 'SMS.ir request failed.');
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status < 200 || $status >= 300 || empty($body['status'])) {
        return new WP_Error('sms_provider_rejected', 'SMS.ir rejected the message.');
    }

    $message_id = isset($body['data']['messageId']) ? (string) $body['data']['messageId'] : hash('sha256', $idempotency_key);
    return array('provider_message_id' => sanitize_text_field($message_id));
}

/**
 * Register SMS.ir as the default SMS provider
 */
add_filter('bamero_sms_provider_send', 'bamero_sms_ir_send', 5, 5);

/**
 * Define SMS template constants for environment configuration
 */
function bamero_sms_template_config() {
    return array(
        'LOGIN_OTP' => array(
            'env_var' => 'SMS_IR_TEMPLATE_LOGIN_OTP',
            'description' => 'OTP for customer login',
            'required' => true,
        ),
        'ORDER_PROCESSING' => array(
            'env_var' => 'SMS_IR_TEMPLATE_ORDER_PROCESSING',
            'description' => 'Order processing notification',
            'required' => true,
        ),
        'ORDER_DELIVERED' => array(
            'env_var' => 'SMS_IR_TEMPLATE_ORDER_DELIVERED',
            'description' => 'Order delivered notification',
            'required' => true,
        ),
        'ORDER_CANCELLED' => array(
            'env_var' => 'SMS_IR_TEMPLATE_ORDER_CANCELLED',
            'description' => 'Order cancelled notification',
            'required' => true,
        ),
        'PAYMENT_FAILED' => array(
            'env_var' => 'SMS_IR_TEMPLATE_PAYMENT_FAILED',
            'description' => 'Payment failed notification',
            'required' => true,
        ),
        'REFUND_COMPLETED' => array(
            'env_var' => 'SMS_IR_TEMPLATE_REFUND_COMPLETED',
            'description' => 'Refund completed notification',
            'required' => true,
        ),
        'CONTACT_REQUEST' => array(
            'env_var' => 'SMS_IR_TEMPLATE_CONTACT_REQUEST',
            'description' => 'Contact request notification',
            'required' => false,
        ),
        'COLOR_CONSULTATION' => array(
            'env_var' => 'SMS_IR_TEMPLATE_COLOR_CONSULTATION',
            'description' => 'Color consultation notification',
            'required' => false,
        ),
    );
}

/**
 * Validate SMS configuration
 */
function bamero_validate_sms_config() {
    $config = bamero_sms_template_config();
    $missing = array();

    foreach ($config as $template => $settings) {
        if ($settings['required'] && !getenv($settings['env_var'])) {
            $missing[] = $template;
        }
    }

    if (!empty($missing)) {
        return new WP_Error('sms_config_incomplete', sprintf('Missing SMS template configuration: %s', implode(', ', $missing)));
    }

    return true;
}

/**
 * SMS health check
 */
function bamero_sms_health_check() {
    $api_key = getenv('SMS_IR_API_KEY');
    $base_url = getenv('SMS_IR_API_BASE_URL');

    return array(
        'provider' => 'SMS.ir',
        'api_key_configured' => !empty($api_key),
        'base_url_configured' => !empty($base_url),
        'templates_configured' => bamero_validate_sms_config() !== true ? false : true,
    );
}
