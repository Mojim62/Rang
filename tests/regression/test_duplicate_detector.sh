#!/usr/bin/env bash
#
# Regression test for tests/check_duplicate_functions.php (SRE gate evidence).
#
# The old detector marked a duplicate as "safe" if ANY declaring file carried a
# function_exists() guard — so a guarded declaration in one file combined with
# an UNGUARDED declaration in another was silently accepted. This test pins the
# corrected contract:
#
#   guarded + unguarded  => detector MUST fail (exit 1)
#   guarded + guarded    => detector MUST pass (exit 0)
#
# Fixtures are generated in a temp dir so the repository tree itself is never
# polluted (the detector scans every *.php file under its target root).
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DET="$ROOT/tests/check_duplicate_functions.php"

command -v php >/dev/null 2>&1 || { echo "REGRESSION FAIL: php binary required." >&2; exit 1; }
[ -f "$DET" ] || { echo "REGRESSION FAIL: detector not found: $DET" >&2; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# ---- Case 1: guarded (file A) + unguarded (file B) => MUST be reported unsafe
cat > "$TMP/a.php" <<'PHP'
<?php
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
    function bamero_csp_nonce() { return 'a'; }
}
PHP
cat > "$TMP/b.php" <<'PHP'
<?php
function bamero_csp_nonce() { return 'b'; }
PHP

if php "$DET" "$TMP" >/dev/null 2>&1; then
    echo "REGRESSION FAIL: guarded+unguarded duplicate was declared SAFE (old bug is back)." >&2
    exit 1
fi
echo "case 1 OK: guarded+unguarded combo is flagged UNSAFE"

# ---- Case 2: guarded in BOTH files => safe
cat > "$TMP/b.php" <<'PHP'
<?php
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
    function bamero_csp_nonce() { return 'b'; }
}
PHP

if ! php "$DET" "$TMP" >/dev/null 2>&1; then
    echo "REGRESSION FAIL: all-guarded duplicate was flagged UNSAFE." >&2
    exit 1
fi
echo "case 2 OK: all-guarded combo is declared SAFE"

# ---- Case 3: single declaration (guarded, alone) => safe (no duplicate at all)
rm "$TMP/a.php"
if ! php "$DET" "$TMP" >/dev/null 2>&1; then
    echo "REGRESSION FAIL: single declaration was flagged UNSAFE." >&2
    exit 1
fi
echo "case 3 OK: single declaration is declared SAFE"

echo "OK: duplicate-detector regression suite passed (3/3)."
exit 0
