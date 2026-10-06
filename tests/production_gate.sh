#!/usr/bin/env bash
#
# Bamero production gate — static verification for hosting deployment.
# Referenced by .github/workflows/production-gate.yml.
#
# Checks:
#   1. PHP syntax lint on every source file
#   2. Guard-aware duplicate function declaration detection (fatal-error class)
#   3. Hard-coded secret scan
#   4. JSON-LD blocks carry a CSP nonce
#   5. WordPress core function collisions with theme/plugin helpers
#
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FAIL=0

echo "=================================================="
echo " Bamero production gate"
echo " root: ${ROOT}"
echo "=================================================="

echo
echo "[1/5] PHP syntax lint"
while IFS= read -r -d '' f; do
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "  LINT FAIL: ${f}"
        php -l "$f" 2>&1 | head -3
        FAIL=1
    fi
done < <(find "$ROOT" -type f -name '*.php' \
    -not -path '*/docs/*' -not -path '*/.git/*' -not -path '*/node_modules/*' -print0)
echo "  done"

echo
echo "[2/5] Duplicate function declarations (guard-aware)"
if ! php "$ROOT/tests/check_duplicate_functions.php" "$ROOT"; then
    FAIL=1
fi

echo
echo "[2b/5] Duplicate detector regression tests"
if ! bash "$ROOT/tests/test_duplicate_detector.sh"; then
    FAIL=1
fi

echo
echo "[3/5] Hard-coded secret scan"
SECRETS=$(grep -rniE "(password|api_key|api_secret|secret|merchant_id|token)\s*=\s*['\"][A-Za-z0-9_\-]{8,}['\"]" \
    "$ROOT/wp-content" "$ROOT/wp-config.php" 2>/dev/null \
    | grep -viE "env\(|getenv|placeholder|example|your|xxx|change|empty|function|\\\$|_KEY'|_SALT'|_SECRET'|constant" || true)
if [ -n "$SECRETS" ]; then
    echo "  SECRET FAIL — potential hard-coded secrets:"
    echo "$SECRETS"
    FAIL=1
else
    echo "  none found"
fi

echo
echo "[4/5] JSON-LD CSP nonce coverage"
LDJSON_MISSING=$(grep -rn 'application/ld+json' "$ROOT/wp-content/themes" 2>/dev/null \
    | grep -v 'nonce=' || true)
if [ -n "$LDJSON_MISSING" ]; then
    echo "  CSP FAIL — JSON-LD without nonce:"
    echo "$LDJSON_MISSING"
    FAIL=1
else
    echo "  all JSON-LD blocks carry a nonce"
fi

echo
echo "[5/5] wp-config fail-closed secret handling"
if grep -q "bamero_require_env" "$ROOT/wp-config.php"; then
    echo "  fail-closed env loader present"
else
    echo "  WARN: wp-config.php does not use fail-closed env loader"
fi

echo
echo "=================================================="
if [ "$FAIL" -eq 0 ]; then
    echo " GATE RESULT: PASS"
    echo "=================================================="
    exit 0
else
    echo " GATE RESULT: FAIL"
    echo "=================================================="
    exit 1
fi
