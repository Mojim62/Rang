#!/usr/bin/env bash
# ساخت بستهٔ استقرار تمیز بامرو (بدون docs، tests، .git و فایل‌های توسعه)
# خروجی: dist/bamero-deploy-<date>.zip
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
OUT_DATE="$(date +%Y%m%d-%H%M)"
OUT="$DIST/bamero-deploy-${OUT_DATE}.zip"

mkdir -p "$DIST"

# فایل‌های استقرار: wp-config.php، wp-content، htaccess-templates (بدون docs/tests/scripts/.github/.git)
cp "$ROOT/wp-config.php" "$STAGE/"
cp -R "$ROOT/wp-content" "$STAGE/wp-content"

# حذف فایل‌های غیر ضروری از بسته
find "$STAGE" -type d \( -name '.git' -o -name 'node_modules' \) -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGE" -type f -name '.DS_Store' -delete 2>/dev/null || true

# بستن zip با ساختار درست
if command -v zip >/dev/null 2>&1; then
    (cd "$STAGE" && zip -r -q "$OUT" wp-config.php wp-content)
else
    echo "خطا: ابزار zip نصب نیست." >&2
    exit 1
fi

rm -rf "$STAGE"
echo "بستهٔ استقرار ساخته شد: ${OUT}"
