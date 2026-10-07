#!/usr/bin/env bash
# تولید خودکار فایل .env برای بامرو — روی هاست (ترمینال/SSH) اجرا کنید.
# خروجی: .env آماده با ۸ کلید salt تولیدشده و placeholderهای لازم
set -euo pipefail

OUT="${1:-.env}"

if [[ -f "$OUT" ]]; then
    echo "خطا: فایل $OUT از قبل وجود دارد. اول آن را پاک یا منتقل کنید." >&2
    exit 1
fi

salt() {
    # 64 کاراکتر تصادفی امن (بدون نیاز به ابزار خارجی)
    head -c 512 /dev/urandom | tr -dc 'a-zA-Z0-9!@#$%^&*()-_=+' | head -c 64
}

DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-localhost}"
WP_HOME="${WP_HOME:-}"

cat > "$OUT" <<ENV
# .env بامرو — تولیدشده در $(date '+%Y-%m-%d %H:%M')
# ⚠️ قبل از نصب: مقادیر DB_* و WP_HOME را اصلاح کنید.
# ⚠️ ZARINPAL_MERCHANT_ID و کلیدهای SMS.ir را دستی جای‌گذاری کنید.

# --- پایگاه‌داده ---
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_HOST=${DB_HOST}
TABLE_PREFIX=wp_bamero_

# --- دامنه ---
WP_HOME=${WP_HOME}
WP_SITEURL=${WP_HOME}

# --- کلیدهای امنیتی (به‌صورت امن تولید شدند) ---
AUTH_KEY='$(salt)'
SECURE_AUTH_KEY='$(salt)'
LOGGED_IN_KEY='$(salt)'
NONCE_KEY='$(salt)'
AUTH_SALT='$(salt)'
SECURE_AUTH_SALT='$(salt)'
LOGGED_IN_SALT='$(salt)'
NONCE_SALT='$(salt)'

# --- پرچم‌های اجرا ---
WP_ENVIRONMENT_TYPE=production
WP_DEBUG=0
WP_DEBUG_LOG=0
DISALLOW_FILE_MODS=1
WP_CACHE=0

# --- شناسهٔ داخلی مشتری ---
BAMERO_INTERNAL_ID_SALT='$(salt)'

# --- SMS.ir (دستی تکمیل کنید) ---
SMS_PROVIDER=sms_ir
SMS_TIMEOUT=120
SMS_IR_API_BASE_URL=https://api.sms.ir/v1/
SMS_IR_API_KEY=
SMS_IR_OTP_PARAMETER=Code
SMS_IR_ORDER_PARAMETER=
SMS_IR_TEMPLATE_LOGIN_OTP=
SMS_IR_TEMPLATE_ORDER_PROCESSING=
SMS_IR_TEMPLATE_ORDER_DELIVERED=
SMS_IR_TEMPLATE_ORDER_CANCELLED=
SMS_IR_TEMPLATE_PAYMENT_FAILED=
SMS_IR_TEMPLATE_REFUND_COMPLETED=

# --- زرین‌پال ---
ZARINPAL_API_BASE_URL=https://payment.zarinpal.com/pg/v4
ZARINPAL_STARTPAY_URL=https://payment.zarinpal.com/pg/StartPay
ZARINPAL_MERCHANT_ID=
ZARINPAL_CURRENCY=IRR
BAMERO_PAYMENT_WEBHOOK_SECRET='$(salt)'

# --- توکن health (برای wp-json/bamero/v1/health/ready) ---
BAMERO_HEALTH_TOKEN='$(salt)'
ENV

chmod 600 "$OUT"
echo "✅ فایل $OUT ساخته شد (سطح دسترسی 600)."
echo "حالا این مقادیر را در آن اصلاح کنید: DB_*، WP_HOME، ZARINPAL_MERCHANT_ID، SMS_IR_API_KEY و templateها."
