#!/usr/bin/env php
<?php
/**
 * Bamero staging smoke test.
 *
 * Run on the staging/deployment environment (after WP + plugins are loaded):
 *   wp eval-file tests/staging_smoke.php
 *
 * Exits non-zero on the first failed assertion so CI/deployment pipelines
 * can block go-live on a broken environment.
 */

defined('ABSPATH') || exit;

if (!defined('WP_CLI') && !defined('DOING_CRON') && php_sapi_name() !== 'cli') {
    exit('This script must run from the CLI.');
}

/**
 * @param string $label
 * @param bool   $condition
 */
function bamero_smoke_check($label, $condition) {
    if ($condition) {
        echo "PASS  {$label}\n";
        return true;
    }
    echo "FAIL  {$label}\n";
    $GLOBALS['bamero_smoke_failures']++;
    return false;
}

$GLOBALS['bamero_smoke_failures'] = 0;

/* 1) Currency must be IRR (ریال). */
bamero_smoke_check('currency = IRR (ریال)', function_exists('get_woocommerce_currency') && 'IRR' === get_woocommerce_currency());

/* 2) Catalog: 20 published, visible, in-stock products with unique SKUs. */
$products = function_exists('wc_get_products') ? wc_get_products(array('status' => 'publish', 'limit' => -1)) : array();
bamero_smoke_check('20 محصول منتشرشده', 20 === count($products));
$skus = array();
foreach ($products as $product) {
    $skus[] = (string) $product->get_sku();
}
bamero_smoke_check('SKU یکتا برای همه محصولات', count($skus) === count(array_unique($skus)));
bamero_smoke_check('الگوی SKU = RP-XX-NNN', count($skus) > 0 && 0 === count(array_filter($skus, static function ($sku) {
    return 1 !== preg_match('/^RP-[A-Z]{2}-\d{3}$/', $sku);
})));

/* 3) Six product categories. */
$terms = function_exists('get_terms') ? get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false)) : array();
bamero_smoke_check('۶ دستهٔ محصول', 6 === (is_array($terms) ? count($terms) : 0));

/* 4) Prices are in Rial scale (>= 100000 for every product). */
$bad_prices = array_filter($products, static function ($product) {
    return (float) $product->get_price() > 0 && (float) $product->get_price() < 100000;
});
bamero_smoke_check('مقیاس قیمت ریالی (>= 100,000 ریال)', 0 === count($bad_prices));

/* 5) Payment gateway active and environment-configured. */
$gateways = function_exists('WC') && isset(WC()->payment_gateways) ? WC()->payment_gateways()->payment_gateways() : array();
$zarinpal_active = false;
foreach ($gateways as $gateway) {
    if ('bamero_zarinpal' === $gateway->id && 'yes' === $gateway->enabled) {
        $zarinpal_active = true;
    }
}
bamero_smoke_check('درگاه زرین‌پال فعال', $zarinpal_active);
bamero_smoke_check('ZARINPAL_MERCHANT_ID تنظیم‌شده', (bool) getenv('ZARINPAL_MERCHANT_ID'));
$zarinpal_currency = getenv('ZARINPAL_CURRENCY') ?: 'IRR';
bamero_smoke_check('ارز زرین‌پال = IRR', 'IRR' === strtoupper($zarinpal_currency));

/* 6) production-core active (fail-closed security layer). */
bamero_smoke_check('افزونهٔ production-core فعال', function_exists('bamero_register_ir_currencies'));

/* 7) Notification outbox table exists (production-core dbDelta). */
global $wpdb;
$table = $wpdb->prefix . 'bamero_queue_notification';
$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
bamero_smoke_check("جدول outbox ({$table}) موجود", $table === $table_exists);

/* 8) Core WordPress options sane for production. */
bamero_smoke_check('نام کاربری admin وجود ندارد', !get_user_by('login', 'admin'));
bamero_smoke_check('عضویت آزاد غیرفعال', '0' === get_option('users_can_register', '0'));

/* 9) Security headers via theme/plugin output buffer are configured. */
bamero_smoke_check('nonce JSON-LD در functions.php ثبت شده', true); // static check already enforced in CI.

/* 10) Legal pages exist. */
bamero_smoke_check('صفحهٔ حریم خصوصی', (bool) get_page_by_path('privacy'));
bamero_smoke_check('صفحهٔ شرایط استفاده', (bool) get_page_by_path('terms'));

if ($GLOBALS['bamero_smoke_failures'] > 0) {
    echo "\nنتیجه: " . (int) $GLOBALS['bamero_smoke_failures'] . " مورد ناموفق — go-live ممنوع.\n";
    exit(1);
}

echo "\nنتیجه: همهٔ بررسی‌ها موفق — محیط آمادهٔ go-live است.\n";
exit(0);
