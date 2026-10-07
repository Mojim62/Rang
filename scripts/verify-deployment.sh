#!/usr/bin/env bash
#
# Bamero post-deploy verification — HTTP checks against a deployed site URL.
# Static inspection does NOT count as deployment verification.
#
# Usage: bash scripts/verify-deployment.sh https://example.com
#
set -euo pipefail

URL="${1:-}"
if [ -z "$URL" ]; then
    echo "VERIFY ABORT: base URL required (e.g. https://staging.example.com)" >&2
    exit 1
fi

FAIL=0

check() { # label, url, expected substring (optional)
    local label="$1" target="$2" expect="${3:-}"
    local code
    # M1 remediation: single HTTP request per check (previously two).
    code=$(curl -sSL --max-time 20 -o /tmp/verify_body -w '%{http_code}' "$target" || echo 000)
    if [ "$code" != "200" ]; then
        echo "  FAIL [$label] HTTP $code for $target"
        FAIL=1
        return
    fi
    if [ -n "$expect" ] && ! grep -q "$expect" /tmp/verify_body; then
        echo "  FAIL [$label] marker '$expect' missing in $target"
        FAIL=1
        return
    fi
    if grep -qiE "(Fatal error|Warning:|Parse error|Deprecated:)" /tmp/verify_body; then
        echo "  FAIL [$label] PHP errors visible in output of $target"
        FAIL=1
        return
    fi
    echo "  OK [$label] $target"
}

echo "== Post-deploy verification: $URL =="

# Application
check "homepage-https-200" "$URL/"
check "wp-json"            "$URL/wp-json/"
check "products"           "$URL/shop/" "bmr"

echo "  INFO: browser/mobile rendering, cart/checkout flow, payment and SMS delivery are NOT covered by this script — they require runtime E2E and must be recorded as NOT VERIFIED until executed."

if [ "$FAIL" -eq 0 ]; then
    echo "VERIFY RESULT: PASS (HTTP-level)"
    exit 0
fi
echo "VERIFY RESULT: FAIL"
exit 1
