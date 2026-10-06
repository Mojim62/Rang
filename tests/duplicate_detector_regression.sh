#!/usr/bin/env bash
# ============================================================================
# Regression test for tests/check_duplicate_functions.php
#
# The detector must treat a function_exists()-guarded declaration in ONE
# file combined with an UNGUARDED declaration of the same function in
# ANOTHER file as UNSAFE. (Load order decides which one fatals; a guard in
# one file never neutralizes an unguarded duplicate elsewhere.)
#
# Case 1: guarded + unguarded  → detector MUST exit 1 (unsafe).
# Case 2: guarded + guarded    → detector MUST exit 0 (safe).
#
# Exit 0 = regression passed, Exit 1 = regression failed.
# ============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DETECTOR="$ROOT/tests/check_duplicate_functions.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [ ! -f "$DETECTOR" ]; then
    echo "FAIL: detector not found at $DETECTOR" >&2
    exit 1
fi

# --- Case 1: guarded in a.php, UNGUARDED in b.php → MUST FAIL ---
cat > "$TMP/a.php" <<'PHP'
<?php
if ( ! function_exists( 'regress_dup' ) ) {
    function regress_dup() { return 1; }
}
PHP
cat > "$TMP/b.php" <<'PHP'
<?php
function regress_dup() { return 2; }
PHP

if php "$DETECTOR" "$TMP" >/dev/null 2>&1; then
    echo "FAIL: detector declared a guarded+unguarded collision SAFE (false-negative)." >&2
    exit 1
fi

# --- Case 2: guarded in BOTH files → MUST PASS ---
cat > "$TMP/b.php" <<'PHP'
<?php
if ( ! function_exists( 'regress_dup' ) ) {
    function regress_dup() { return 2; }
}
PHP

if ! php "$DETECTOR" "$TMP" >/dev/null 2>&1; then
    echo "FAIL: detector flagged a fully-guarded duplicate as unsafe (false-positive)." >&2
    exit 1
fi

echo "OK: duplicate-function detector regression passed (guarded+unguarded=unsafe, all-guarded=safe)."
exit 0
