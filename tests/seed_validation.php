#!/usr/bin/env php
<?php
/**
 * Seed validation — بامرو
 *
 * فقط برای اعتبارسنجی دیتای اولیهٔ (seed/demo) بعد از راه‌اندازی اولیه:
 *   wp eval-file tests/seed_validation.php
 *
 * در سایت live واقعی اجرا نشود (افزودن محصول ۲۱، «خرابی» نیست!) —
 * برای production از tests/health_check.php استفاده کنید.
 */

defined('ABSPATH') || exit;

$GLOBALS['bamero_seed_failures'] = 0;

function bamero_seed_check($label, $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) { $GLOBALS['bamero_seed_failures']++; }
    return $ok;
}

/* Seed: currency must be Rial (IRR) */
bamero_seed_check('Seed currency = IRR', function_exists('get_woocommerce_currency') && 'IRR' === get_woocommerce_currency());

/* Seed: catalog definition */
$products = function_exists('wc_get_products') ? wc_get_products(array('status' => 'publish', 'limit' => -1)) : array();
bamero_seed_check('۲۰ محصول seed', 20 === count($products));

$skus = array_map(static function ($p) { return (string) $p->get_sku(); }, $products);
bamero_seed_check('SKU یکتا', count($skus) === count(array_unique($skus)));
bamero_seed_check('الگوی SKU = RP-XX-NNN', count($skus) > 0 && 0 === count(array_filter($skus, static function ($s) {
    return 1 !== preg_match('/^RP-[A-Z]{2}-\d{3}$/', $s);
})));

$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
bamero_seed_check('۶ دستهٔ seed', 6 === (is_array($terms) ? count($terms) : 0));

/* Seed: Rial price scale (no leftover toman-scale prices) */
$bad = array_filter($products, static function ($p) {
    return (float) $p->get_price() > 0 && (float) $p->get_price() < 100000;
});
bamero_seed_check('مقیاس قیمت ریالی', 0 === count($bad));

/* Seed: legal pages created by custom-plugin */
bamero_seed_check('صفحهٔ حریم خصوصی ساخته شده', (bool) get_page_by_path('privacy'));
bamero_seed_check('صفحهٔ شرایط استفاده ساخته شده', (bool) get_page_by_path('terms'));

if (!defined('BAMERO_SMOKE_RUNNER')) {
    if ($GLOBALS['bamero_seed_failures'] > 0) {
        echo "\nنتیجهٔ seed: " . (int) $GLOBALS['bamero_seed_failures'] . " مورد ناموفق — سید کامل نصب نشده است.\n";
        exit(1);
    }
    echo "\nنتیجهٔ seed: دیتای اولیه کامل و معتبر است.\n";
    exit(0);
}
