#!/usr/bin/env php
<?php
/**
 * Staging smoke — بامرو (runner)
 *
 * اجرای هر دو گیت روی staging بعد از راه‌اندازی کامل:
 *   wp eval-file tests/staging_smoke.php
 *
 * 1) tests/health_check.php    → سلامت محیط production (محیط‌آگنوستیک)
 * 2) tests/seed_validation.php → اعتبار دیتای اولیه (فقط staging/اولین نصب)
 */

defined('ABSPATH') || exit;

define('BAMERO_SMOKE_RUNNER', true);

require __DIR__ . '/health_check.php';
echo "\n";

$GLOBALS['bamero_smoke_total_failures'] = $GLOBALS['bamero_health_failures'];

require __DIR__ . '/seed_validation.php';
$GLOBALS['bamero_smoke_total_failures'] += $GLOBALS['bamero_seed_failures'];

if ($GLOBALS['bamero_smoke_total_failures'] > 0) {
    echo "\nنتیجهٔ staging smoke: " . (int) $GLOBALS['bamero_smoke_total_failures'] . " مورد ناموفق — go-live ممنوع.\n";
    exit(1);
}
echo "\nنتیجهٔ staging smoke: همهٔ بررسی‌ها موفق — محیط آمادهٔ go-live است.\n";
exit(0);
