#!/usr/bin/env bash
# بوت‌استرپ نصب اولیهٔ بامرو (local/staging) — یک‌بار اجرا، همه‌چیز آماده.
# ⚠️ این اسکریپت مسیر deployment تولیدی نیست؛ بستهٔ استقرار فقط با scripts/build-release.sh ساخته می‌شود.
# پیش‌نیاز: فایل‌های پروژه در public_html، فایل .env ساخته‌شده (scripts/make-env.sh)،
#           wp-cli در دسترس، و وردپرس از طریق install.php نصب‌شده.
# اجرا: bash scripts/quick-install.sh  (از داخل public_html)
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

WP="wp"
command -v wp >/dev/null 2>&1 || { echo "خطا: wp-cli نصب نیست (curl فایل phar از wp-cli.org)." >&2; exit 1; }

echo "── ۱/۶ بررسی اتصال وردپرس…"
$WP core is-installed || { echo "خطا: وردپرس هنوز نصب نیست. اول /wp-admin/install.php را کامل کنید." >&2; exit 1; }
$WP core verify-checksums --quiet 2>/dev/null || true

echo "── ۲/۶ نصب WooCommerce و افزونه‌های پیشنهادی از مخزن…"
# توجه: با DISALLOW_FILE_MODS=1 وردپرس نصب افزونه از مخزن را بلاک می‌کند؛ در صورت خطا همان‌طور که پیام راهنما می‌گوید موقتاً 0 کنید و بعد برگردانید
$WP plugin install woocommerce --activate || { echo "خطا: نصب ووکامرس ناموفق. DISALLOW_FILE_MODS را موقتاً 0 کنید." >&2; exit 1; }
$WP plugin install wordpress-seo --activate 2>/dev/null \
  || $WP plugin install seo-by-rank-math --activate 2>/dev/null \
  || echo "   (افزونهٔ SEO بعداً دستی نصب کنید)"

echo "── ۳/۶ فعال‌سازی تم و افزونه‌های بامرو…"
$WP theme activate bamero
$WP plugin activate \
    bamero-production-core \
    bamero-mobile-auth \
    bamero-zarinpal-gateway \
    bamero-woocommerce-setup \
    bamero-custom-plugin \
    bamero-essential-plugins

echo "── ۴/۶ تنظیمات پایه…"
$WP rewrite structure '/%postname%/' --hard
$WP option update timezone_string 'Asia/Tehran'
$WP option update woocommerce_currency 'IRR'
$WP option update blog_public '1'

echo "── ۵/۶ کش‌کردن rewrite و flush…"
$WP rewrite flush --hard

echo "── ۶/۶ دود-تست سلامت محیط…"
if $WP eval-file tests/staging_smoke.php; then
    echo ""
    echo "✅ نصب خودکار کامل شد. فروشگاه آمادهٔ تست پرداخت است."
    echo "   یادآوری: admin نام کاربری مدیر نباشد و ZARINPAL_MERCHANT_ID در .env گذاشته شود."
else
    echo ""
    echo "❌ دود-تست شکست خورد — موارد FAIL بالا را رفع و دوباره اجرا کنید." >&2
    exit 1
fi
