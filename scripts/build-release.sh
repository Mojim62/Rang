#!/usr/bin/env bash
# ============================================================================
# bamero-release builder — تنها (canonical) سازندهٔ بستهٔ استقرار
# هم برای local developer، هم برای GitHub Actions (build-release.yml)
# خروجی: dist/bamero-release.zip
#   ├── wp-config.php
#   ├── .htaccess
#   ├── wp-content/
#   ├── VERSION
#   └── SHA256SUMS
# ============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
OUT="$DIST/bamero-release.zip"
trap 'rm -rf "$STAGE"' EXIT

# --- پیش‌نیازها (fail-fast) ---
for f in wp-config.php .htaccess wp-content; do
    if [ ! -e "$ROOT/$f" ]; then
        echo "خطا: $f در ریشهٔ پروژه یافت نشد — بستهٔ استقرار بدون آن معتبر نیست." >&2
        exit 1
    fi
done
command -v zip >/dev/null 2>&1 || { echo "خطا: ابزار zip نصب نیست." >&2; exit 1; }

# --- نسخه: تگ git، وگرنه SHA کوتاه + تاریخ ---
VERSION="$(git -C "$ROOT" describe --tags --always --dirty 2>/dev/null || echo "unknown-$(date +%Y%m%d-%H%M)")"

mkdir -p "$DIST" "$STAGE/bamero-release"

# --- محتوای مجاز بسته: فقط این سه + متادیتا ---
cp "$ROOT/wp-config.php"  "$STAGE/bamero-release/wp-config.php"
cp "$ROOT/.htaccess"      "$STAGE/bamero-release/.htaccess"
cp -R "$ROOT/wp-content"  "$STAGE/bamero-release/wp-content"

# --- پاکسازی هرگونه باقیماندهٔ توسعه از wp-content ---
find "$STAGE" -type d \( -name '.git' -o -name 'node_modules' \) -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGE" -type f \( -name '.DS_Store' -o -name '.env' -o -name '*.log' \) -delete 2>/dev/null || true
# محافظت: هیچ secret نباید داخل بسته باشد
if grep -rqI --exclude-dir='assets' --exclude-dir='fonts' -E 'ZARINPAL_MERCHANT_ID=[0-9a-f]{36}|SMS_IR_API_KEY=\.+' "$STAGE" 2>/dev/null; then
    echo "خطا: نشانهٔ credential داخل بسته یافت شد — ساخت متوقف شد." >&2
    exit 1
fi

# --- متادیتا ---
echo "$VERSION" > "$STAGE/bamero-release/VERSION"
( cd "$STAGE/bamero-release" && find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS )

# --- ZIP ---
( cd "$STAGE/bamero-release" && zip -r -q "$OUT" . )

echo "OK: $OUT  (version: $VERSION, $(du -h "$OUT" | cut -f1))"
