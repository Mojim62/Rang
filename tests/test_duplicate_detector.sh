#!/usr/bin/env bash
#
# Regression tests for tests/check_duplicate_functions.php
# (guard-aware duplicate function detector).
#
# Creates isolated fixture directories in a temp dir (never inside the repo, so
# the repo-wide detector run is not polluted) and asserts the detector's exit
# code for each pattern:
#
#   1. unguarded duplicate across 2 files        -> MUST FAIL
#   2. MIXED guard (guarded in one file,
#      unguarded in the other)                   -> MUST FAIL  (old detector said safe)
#   3. guarded in both files                     -> MUST PASS
#   4. two unguarded declarations where one file
#      merely *uses* function_exists() (no guard) -> MUST FAIL (old detector said safe)
#   5. same-file redeclaration                   -> MUST FAIL
#
# Exit 0 = all regression cases behave as expected.

set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DETECTOR="$HERE/check_duplicate_functions.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

FAIL=0

run_case() {
    local dir="$1"; local expect="$2"; local desc="$3"
    if php "$DETECTOR" "$dir" >/dev/null 2>&1; then
        local got="pass"
    else
        local got="fail"
    fi
    if [ "$got" = "$expect" ]; then
        echo "  OK   [${desc}]"
    else
        echo "  FAIL [${desc}] expected=${expect} got=${got}"
        FAIL=1
    fi
}

# --- Case 1: unguarded duplicate across two files -> FAIL
D="$TMP/case1_unguarded"; mkdir -p "$D"
cat > "$D/a.php" <<'EOF'
<?php
function bamero_test_dup() { return 1; }
EOF
cat > "$D/b.php" <<'EOF'
<?php
function bamero_test_dup() { return 2; }
EOF
run_case "$D" fail "unguarded duplicate detected"

# --- Case 2: MIXED guard -> FAIL (the load-order trap; old detector said safe)
D="$TMP/case2_mixed"; mkdir -p "$D"
cat > "$D/a.php" <<'EOF'
<?php
function bamero_test_mixed() { return 1; }
EOF
cat > "$D/b.php" <<'EOF'
<?php
if ( ! function_exists( 'bamero_test_mixed' ) ) {
    function bamero_test_mixed() { return 2; }
}
EOF
run_case "$D" fail "mixed guard (guarded in one file, unguarded in the other) flagged as unsafe"

# --- Case 3: guarded in both files -> PASS
D="$TMP/case3_guarded"; mkdir -p "$D"
cat > "$D/a.php" <<'EOF'
<?php
if ( ! function_exists( 'bamero_test_guarded' ) ) {
    function bamero_test_guarded() { return 1; }
}
EOF
cat > "$D/b.php" <<'EOF'
<?php
if ( ! function_exists( 'bamero_test_guarded' ) ) {
    function bamero_test_guarded() { return 2; }
}
EOF
run_case "$D" pass "all declarations guarded reported safe"

# --- Case 4: usage-level function_exists must NOT count as a guard -> FAIL
D="$TMP/case4_usage"; mkdir -p "$D"
cat > "$D/a.php" <<'EOF'
<?php
function bamero_test_usage() { return 1; }
EOF
cat > "$D/b.php" <<'EOF'
<?php
function bamero_test_usage() { return 2; }
$x = function_exists( 'bamero_test_usage' ) ? bamero_test_usage() : '';
EOF
run_case "$D" fail "bare function_exists() usage does not count as a guard"

# --- Case 5: same-file redeclaration -> FAIL
D="$TMP/case5_samefile"; mkdir -p "$D"
cat > "$D/a.php" <<'EOF'
<?php
function bamero_test_same() { return 1; }
function bamero_test_same() { return 2; }
EOF
run_case "$D" fail "same-file redeclaration detected"

echo
if [ "$FAIL" -eq 0 ]; then
    echo "DETECTOR REGRESSION: ALL PASS"
    exit 0
fi
echo "DETECTOR REGRESSION: FAILURES DETECTED"
exit 1
