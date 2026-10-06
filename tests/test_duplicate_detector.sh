#!/usr/bin/env bash
#
# Regression tests for tests/check_duplicate_functions.php (guard-aware v2).
#
# Case 1 (THE REGRESSION): guarded declaration in file A + unguarded declaration
#         of the same name in file B => detector MUST FAIL.
#         (v1 wrongly marked this safe when any file mentioned function_exists.)
# Case 2: all declarations guarded => detector MUST PASS.
# Case 3: single declaration, unguarded => detector MUST PASS.
#
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mk_plugin() {
    mkdir -p "$TMP/wp-content/plugins/testplug"
}

expect_fail() { # $1=label
    if php "$ROOT/tests/check_duplicate_functions.php" "$TMP" >/dev/null 2>&1; then
        echo "  REGRESSION FAIL (detector wrongly PASSed): $1"
        return 1
    fi
    echo "  PASS: $1"
    return 0
}

expect_pass() { # $1=label
    if ! php "$ROOT/tests/check_duplicate_functions.php" "$TMP" >/dev/null 2>&1; then
        echo "  FAIL (detector wrongly FAILed): $1"
        return 1
    fi
    echo "  PASS: $1"
    return 0
}

FAIL=0

# ---- Case 1: guarded in one file, unguarded in another => must FAIL ----
mk_plugin
cat > "$TMP/wp-content/plugins/testplug/a.php" <<'PHP'
<?php
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
function bamero_csp_nonce() {
    return 'a';
}
}
PHP
cat > "$TMP/wp-content/plugins/testplug/b.php" <<'PHP'
<?php
// mentions function_exists but NOT around the declaration
function bamero_csp_nonce() {
    return 'b';
}
add_action('x', function () { return function_exists('bamero_csp_nonce'); });
PHP
echo "[case 1] guarded + unguarded duplicate must fail"
expect_fail "guarded+unguarded pair" || FAIL=1
rm -rf "$TMP/wp-content"

# ---- Case 2: all guarded => must pass ----
mkdir -p "$TMP/wp-content/plugins/testplug"
cat > "$TMP/wp-content/plugins/testplug/a.php" <<'PHP'
<?php
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
function bamero_csp_nonce() {
    return 'a';
}
}
PHP
cat > "$TMP/wp-content/plugins/testplug/b.php" <<'PHP'
<?php
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
function bamero_csp_nonce() {
    return 'b';
}
}
PHP
echo "[case 2] all-guarded pair must pass"
expect_pass "all guarded" || FAIL=1
rm -rf "$TMP/wp-content"

# ---- Case 3: single declaration => must pass ----
mkdir -p "$TMP/wp-content/plugins/testplug"
cat > "$TMP/wp-content/plugins/testplug/a.php" <<'PHP'
<?php
function bamero_only_one() {
    return 'x';
}
PHP
echo "[case 3] single declaration must pass"
expect_pass "single declaration" || FAIL=1

echo
if [ "$FAIL" -eq 0 ]; then
    echo "DETECTOR REGRESSION TESTS: PASS"
    exit 0
fi
echo "DETECTOR REGRESSION TESTS: FAIL"
exit 1
