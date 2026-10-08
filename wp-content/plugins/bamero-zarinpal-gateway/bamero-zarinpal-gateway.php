<?php
/**
 * Plugin Name: Bamero Zarinpal Gateway
 * Description: Production WooCommerce gateway for the official Zarinpal v4 request/verify flow. Configuration is WooCommerce-settings backed with environment fallback; no credential is ever stored in code or the database.
 * Version: 1.1.0
 * Author: Bamero
 * License: MIT
 * Text Domain: bamero-zarinpal-gateway
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;

define('BAMERO_ZARINPAL_VERSION', '1.1.0');

/**
 * Declare High-Performance Order Storage (HPOS) compatibility.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'bamero_zarinpal_bootstrap', 20);
function bamero_zarinpal_bootstrap() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class Bamero_Zarinpal_Gateway extends WC_Payment_Gateway {
        /** @var string */
        public $merchant_id = '';
        /** @var string */
        public $api_base = '';
        /** @var string */
        public $startpay_base = '';
        /** @var string */
        public $currency = 'IRR';

        public function __construct() {
            $this->id                 = 'bamero_zarinpal';
            $this->method_title       = 'زرین‌پال';
            $this->method_description = 'درگاه رسمی زرین‌پال (v4). پیکربندی از تنظیمات ووکامرس خوانده می‌شود و در صورت نبود، از متغیرهای محیطی استفاده می‌کند؛ هیچ credential در کد یا دیتابیس ذخیره نمی‌شود.';
            $this->has_fields         = false;
            $this->supports           = array('products');

            $this->init_form_fields();
            $this->init_settings();

            // Production-only flag: Payment is disabled in staging unless explicitly enabled
            $is_staging = getenv('BAMERO_ENVIRONMENT') === 'staging';
            $enable_in_staging = getenv('BAMERO_ENABLE_PAYMENT_IN_STAGING') === 'true';
            if ($is_staging && !$enable_in_staging) {
                $this->enabled = 'no';
                $this->description = '\u062f\u0631\u062f\u0627\u062e\u062a \u0627\u06cc\u0646\u062f \u0628\u0627\u0633 \u0632\u0631\u06cc\u0646\u0654\u067e\u0627\u0644 \u0627\u0632 \u0645\u062d\u06cc\u0627 staging \u0648\u0627\u0642\u0639 \u0627\u0633\u062a. \u062a\u0646\u0644\u06cc\u0647 \u062f\u0631\u0644\u0627\u062e BAMERO_ENABLE_PAYMENT_IN_STAGING=true \u0627\u0646\u062c\u0627\u0645 \u062b\u0633 \u0648\u0627\u0631\u062f.';
                add_action('admin_notices', function() { echo '<div class="notice notice-warning"><p>' . esc_html__('Zarinpal payment is disabled in staging. Set BAMERO_ENABLE_PAYMENT_IN_STAGING=true to enable.', 'bamero-zarinpal-gateway') . '</p></div>'; });
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_bamero_zarinpal', array($this, 'handle_callback'));
                return;
            }
            // Settings-backed configuration with environment fallback.
            // Environment-only credential: the merchant ID never lives in the database.
            $this->merchant_id   = (string) (getenv('ZARINPAL_MERCHANT_ID') ?: '');
            $this->api_base      = rtrim($this->cfg('api_base_url', 'ZARINPAL_API_BASE_URL', 'https://payment.zarinpal.com/pg/v4'), '/');
            $this->startpay_base = rtrim($this->cfg('startpay_url', 'ZARINPAL_STARTPAY_URL', 'https://payment.zarinpal.com/pg/StartPay'), '/');
            $this->currency      = strtoupper($this->cfg('currency', 'ZARINPAL_CURRENCY', 'IRR'));

            $this->title       = $this->get_option('title', 'پرداخت امن زرین‌پال');
            $this->description = $this->get_option('description', 'پس از ثبت سفارش به درگاه زرین‌پال منتقل می‌شوید.');

            // Fail closed: the gateway is only usable when fully configured.
            $this->enabled = ($this->is_configured() && 'yes' === $this->get_option('enabled', 'yes')) ? 'yes' : 'no';

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_bamero_zarinpal', array($this, 'handle_callback'));
        }

        /** Read a setting, falling back to an environment variable, then a default. */
        private function cfg($key, $env, $fallback = '') {
            $val = $this->get_option($key, '');
            if ('' === $val || null === $val) {
                $env_val = getenv($env);
                $val = (false === $env_val) ? '' : $env_val;
            }
            return ('' === $val || null === $val) ? $fallback : $val;
        }

        private function is_configured() {
            return '' !== $this->merchant_id && '' !== $this->api_base && '' !== $this->startpay_base;
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'فعال‌سازی',
                    'type'    => 'checkbox',
                    'label'   => 'فعال کردن درگاه زرین‌پال',
                    'default' => getenv('ZARINPAL_MERCHANT_ID') ? 'yes' : 'no',
                ),
                'title' => array(
                    'title'       => 'عنوان',
                    'type'        => 'text',
                    'description' => 'عنوان نمایش‌داده‌شده در صفحه پرداخت.',
                    'default'     => 'پرداخت امن زرین‌پال',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'   => 'توضیحات',
                    'type'    => 'textarea',
                    'default' => 'پس از ثبت سفارش به درگاه زرین‌پال منتقل می‌شوید.',
                ),
                'merchant_id_note' => array(
                    'title'       => 'Merchant ID',
                    'type'        => 'title',
                    'description' => 'شناسه ۳۶ کاراکتری پذیرنده فقط از متغیر محیطی ZARINPAL_MERCHANT_ID (فایل .env خارج از ریشهٔ وب) خوانده می‌شود و به دلخواه امنیتی در پایگاه‌داده ذخیره نمی‌شود.',
                ),
                'currency' => array(
                    'title'   => 'واحد پول',
                    'type'    => 'select',
                    'options' => array('IRR' => 'ریال (IRR)', 'IRT' => 'تومان (IRT)'),
                    'default' => getenv('ZARINPAL_CURRENCY') ?: 'IRR',
                ),
                'api_base_url' => array(
                    'title'       => 'آدرس API',
                    'type'        => 'text',
                    'description' => 'پیش‌فرض رسمی: https://payment.zarinpal.com/pg/v4 (سندباکس: https://sandbox.zarinpal.com/pg/v4).',
                    'default'     => getenv('ZARINPAL_API_BASE_URL') ?: 'https://payment.zarinpal.com/pg/v4',
                    'desc_tip'    => true,
                ),
                'startpay_url' => array(
                    'title'       => 'آدرس StartPay',
                    'type'        => 'text',
                    'description' => 'پیش‌فرض رسمی: https://payment.zarinpal.com/pg/StartPay',
                    'default'     => getenv('ZARINPAL_STARTPAY_URL') ?: 'https://payment.zarinpal.com/pg/StartPay',
                    'desc_tip'    => true,
                ),
            );
        }

        /** Log through the shared production logger when available. */
        private function log($event, array $context = array()) {
            if (function_exists('bamero_log_event')) {
                bamero_log_event($event, $context);
            }
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return array('result' => 'failure');
            }
            if (!$this->is_configured()) {
                wc_add_notice('درگاه پرداخت هنوز پیکربندی نشده است.', 'error');
                return array('result' => 'failure');
            }

            $amount = (int) round((float) $order->get_total());
            if ($amount <= 0) {
                wc_add_notice('مبلغ سفارش برای پرداخت معتبر نیست.', 'error');
                return array('result' => 'failure');
            }

            $currency = strtoupper((string) $order->get_currency());
            if (!in_array($currency, array('IRR', 'IRT'), true)) {
                $currency = $this->currency;
            }

            $callback = add_query_arg('wc-api', 'bamero_zarinpal', home_url('/'));

            $response = wp_remote_post($this->api_base . '/payment/request.json', array(
                'timeout' => 20,
                'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
                'body'    => wp_json_encode(array(
                    'merchant_id'  => $this->merchant_id,
                    'amount'       => $amount,
                    'currency'     => $currency,
                    'description'  => 'Bamero order #' . $order->get_id(),
                    'callback_url' => $callback,
                    'metadata'     => array(
                        'mobile'   => (string) $order->get_billing_phone(),
                        'order_id' => (string) $order->get_id(),
                    ),
                )),
            ));

            if (is_wp_error($response)) {
                $this->log('zarinpal_request_transport_error', array('order_id' => $order->get_id()));
                wc_add_notice('ارتباط با درگاه پرداخت برقرار نشد.', 'error');
                return array('result' => 'failure');
            }

            $http_code = (int) wp_remote_retrieve_response_code($response);
            $body   = json_decode(wp_remote_retrieve_body($response), true);
            $code   = isset($body['data']['code']) ? (int) $body['data']['code'] : 0;
            $authority = isset($body['data']['authority']) ? sanitize_text_field((string) $body['data']['authority']) : '';

            if ($http_code < 200 || $http_code >= 300 || 100 !== $code || '' === $authority) {
                $this->log('zarinpal_request_rejected', array('order_id' => $order->get_id(), 'code' => $code));
                wc_add_notice('درخواست پرداخت رد شد. بعداً دوباره تلاش کنید.', 'error');
                return array('result' => 'failure');
            }

            $order->update_meta_data('_bamero_zarinpal_authority', $authority);
            $order->update_meta_data('_bamero_zarinpal_currency', $currency);
            // R2: snapshot the exact amount sent to the gateway; verify must
            // reuse THIS value, not a re-read of the live order total (an
            // admin edit/refund between request and callback would desync
            // verify — ZarinPal code 54 — and fail an already-paid order).
            $order->update_meta_data('_bamero_zarinpal_amount', $amount);
            $order->save();
            $order->update_status('pending', 'در انتظار بازگشت از زرین‌پال.');

            $this->log('zarinpal_request_ok', array('order_id' => $order->get_id()));

            // R7: fast authority→order lookup for the callback; avoids an
            // unindexed meta_query on every gateway redirect.
            set_transient('bamero_zp_au_' . md5($authority), $order->get_id(), 7 * DAY_IN_SECONDS);

            return array(
                'result'   => 'success',
                'redirect' => $this->startpay_base . '/' . rawurlencode($authority),
            );
        }

        public function handle_callback() {
            $authority = isset($_GET['Authority']) ? sanitize_text_field(wp_unslash($_GET['Authority'])) : '';
            $status    = isset($_GET['Status']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['Status']))) : '';

            // R7: transient fast-path first; meta_query only as fallback for
            // legacy orders created before the fast-path existed.
            $order = false;
            if ($authority) {
                $fast_id = (int) get_transient('bamero_zp_au_' . md5($authority));
                if ($fast_id) {
                    $order = wc_get_order($fast_id);
                }
                if (!$order) {
                    $orders = wc_get_orders(array('limit' => 1, 'return' => 'objects', 'meta_key' => '_bamero_zarinpal_authority', 'meta_value' => $authority));
                    $order = !empty($orders) ? $orders[0] : false;
                }
            }

            if (!$order) {
                $this->log('zarinpal_callback_unknown_authority');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            if ('OK' !== $status) {
                $order->update_status('failed', 'بازگشت ناموفق از زرین‌پال.');
                $this->log('zarinpal_callback_not_ok', array('order_id' => $order->get_id()));
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            // Idempotency: never re-verify or re-complete a paid order.
            if ($order->is_paid()) {
                wp_safe_redirect($this->get_return_url($order));
                exit;
            }

            // R2: verify with the amount captured at request time (fallback to
            // the live total only for legacy orders without the snapshot).
            $amount = (int) $order->get_meta('_bamero_zarinpal_amount');
            if ($amount <= 0) {
                $amount = (int) round((float) $order->get_total());
            }

            // The verify amount must be in the SAME currency unit that was used
            // for the payment request, otherwise ZarinPal rejects with code 54
            // (amount mismatch). The unit used at request time is persisted on
            // the order so the callback is immune to later settings changes.
            $currency = strtoupper((string) $order->get_meta('_bamero_zarinpal_currency'));
            if (!in_array($currency, array('IRR', 'IRT'), true)) {
                $currency = $this->currency;
            }

            $response = wp_remote_post($this->api_base . '/payment/verify.json', array(
                'timeout' => 20,
                'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
                'body'    => wp_json_encode(array(
                    'merchant_id' => $this->merchant_id,
                    'amount'      => $amount,
                    'currency'    => $currency,
                    'authority'   => $authority,
                )),
            ));

            $http_code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
            $body = is_wp_error($response) ? array() : json_decode(wp_remote_retrieve_body($response), true);
            $code = isset($body['data']['code']) ? (int) $body['data']['code'] : 0;

            // 100 = verified now, 101 = already verified (idempotent success).
            if ($http_code >= 200 && $http_code < 300 && (100 === $code || 101 === $code)) {
                $ref = isset($body['data']['ref_id']) ? sanitize_text_field((string) $body['data']['ref_id']) : $authority;
                $order->update_meta_data('_bamero_zarinpal_ref_id', $ref);
                $order->save();
                if (!$order->is_paid()) {
                    $order->payment_complete($ref);
                }
                $order->add_order_note('پرداخت زرین‌پال تأیید شد: ' . $ref);
                $this->log('zarinpal_verify_ok', array('order_id' => $order->get_id(), 'code' => $code));
                wp_safe_redirect($this->get_return_url($order));
                exit;
            }

            $order->update_status('failed', 'تأیید پرداخت زرین‌پال ناموفق بود.');
            $this->log('zarinpal_verify_failed', array('order_id' => $order->get_id(), 'code' => $code));
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'Bamero_Zarinpal_Gateway';
        return $gateways;
    });
}
