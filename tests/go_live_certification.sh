#!/usr/bin/env bash
#
# Bamero go-live certification — static acceptance suite (2026-10).
# Referenced by .github/workflows/production-gate.yml.
#
# Every go-live requirement that is provable from the repository itself is
# pinned here as a deterministic check. The suite fails closed: exit 0 only
# when every check passes. CI captures the full report as
# artifact/go-live-certification.txt and ships it with every build, giving
# per-SHA evidence of go-live readiness.
#
# Usage:
#   bash tests/go_live_certification.sh
#   make certify
#
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
THEME="$ROOT/wp-content/themes/bamero"
WC="$ROOT/wp-content"

PASS=0
FAIL=0

ok()  { PASS=$((PASS+1)); echo "  PASS  [$1] $2"; }
bad() { FAIL=$((FAIL+1)); echo "  FAIL  [$1] $2"; }

# ck <id> <description> <condition>  (condition is eval'd; must succeed)
ck() {
    local id="$1" desc="$2" cond="$3"
    if eval "$cond" >/dev/null 2>&1; then ok "$id" "$desc"; else bad "$id" "$desc"; fi
}

echo "=================================================="
echo " Bamero go-live certification (static)"
echo " root: ${ROOT}"
echo "=================================================="

echo
echo "[A] Release packaging and environment contract"
ck A1 "release bundle prerequisites present"   'for f in wp-config.php .htaccess health-check.php wp-content; do [ -e "$ROOT/$f" ] || exit 1; done'
ck A2 "wp-config fail-closed env loader"   'grep -q "bamero_require_env" "$ROOT/wp-config.php"'
ck A3 ".env.example present"   '[ -f "$ROOT/.env.example" ]'
ck A4 "release builder present"   '[ -f "$ROOT/scripts/build-release.sh" ]'
ck A5 "deploy/verify/rollback transport scripts present"   'for s in deploy-release.sh verify-deployment.sh rollback-release.sh; do [ -f "$ROOT/scripts/$s" ] || exit 1; done'

echo
echo "[B] Theme architecture (v2.2.0 modular)"
ck B1 "style.css Version: 2.2.0"   'grep -q "^Version: 2.2.0" "$THEME/style.css"'
ck B2 "BAMERO_VERSION constant is 2.2.0"   'grep -q "define(.BAMERO_VERSION., .2.2.0.);" "$THEME/functions.php"'
ck B3 "functions.php is a lean bootstrap (no function definitions)"   '! grep -qE "function +bamero_" "$THEME/functions.php"'
ck B4 "all seven inc/ modules present"   'for m in helpers setup woocommerce seo performance security forms; do [ -f "$THEME/inc/$m.php" ] || exit 1; done'
ck B5 "inc/ modules required exactly once, in canonical order"   'diff <(printf "%s\n" helpers setup woocommerce seo performance security forms) <(grep -oE "/inc/[a-z]+\.php" "$THEME/functions.php" | sed -E "s#/inc/([a-z]+)\.php#\1#")'
ck B6 "ABSPATH guard in every theme PHP module"   'for f in "$THEME/functions.php" "$THEME"/inc/*.php; do grep -q "defined(.ABSPATH.) || exit;" "$f" || exit 1; done'
ck B7 "no duplicate bamero_* function definitions across the theme"   '! grep -rhoE "function +bamero_[a-zA-Z0-9_]+" "$THEME" --include="*.php" | sort | uniq -d | grep -q .'
ck B8 "theme.json parses as JSON"   'ruby -rjson -e "JSON.parse(File.read(ARGV[0]))" "$THEME/theme.json"'

echo
echo "[C] Security contracts"
ck C1 "product search form escapes the query (XSS)"   'grep -qF "esc_attr(get_search_query())" "$THEME/inc/woocommerce.php"'
ck C2 "GATE-03: cart fragment selector key span.cart-count"   'grep -q "fragments\[.span.cart-count.\]" "$THEME/inc/woocommerce.php"'
ck C3 "GATE-03: header renders span.cart-count"   'grep -qF "<span class=\"cart-count\">" "$THEME/header.php"'
ck C4 "every JSON-LD block carries a CSP nonce"   '! grep -rn "application/ld+json" "$THEME" --include="*.php" | grep -v "nonce=" | grep -q .'
ck C5 "CSP nonce callback guarded (plugin owns the canonical definition)"   'grep -q "function_exists(.bamero_csp_nonce.)" "$THEME/inc/security.php"'
ck C6 "wp_generator removed from wp_head"   'grep -q "remove_action(.wp_head., .wp_generator.)" "$THEME/functions.php"'
ck C7 "XML-RPC methods disabled"   'grep -q "xmlrpc_methods" "$THEME/inc/security.php"'

echo
echo "[D] Asset and dead-code hygiene"
ck D1 "theme images ship SVG only"   '! find "$THEME/images" -type f ! -name "*.svg" | grep -q .'
ck D2 "no references to the deleted modern-commerce-2026.css"   '! grep -rq "modern-commerce-2026" "$WC" --include="*.php" --include="*.css" --include="*.js"'
ck D3 "no references to the deleted checkout template override"   '! grep -rq "checkout/form-checkout" "$WC" --include="*.php"'
ck D4 "no references to deleted PNG icon assets"   '! grep -rqE "(favicon|logo|apple-touch-icon)[0-9]*\.png" "$THEME" --include="*.php"'
ck D5 "removed quick-view label absent from templates"   '! grep -rq "مشاهده سریع" "$THEME" --include="*.php"'
ck D6 "dead .filter-chips CSS absent"   '! grep -rq "filter-chips" "$THEME/css"'

echo
echo "[E] Performance contracts (Core Web Vitals)"
ck E1 "LCP priority hint on the front-page product image"   'grep -q "fetchpriority" "$THEME/front-page.php"'
ck E2 "WooCommerce core CSS dequeued in favor of curated stylesheets"   'grep -q "woocommerce_enqueue_styles" "$THEME/inc/woocommerce.php"'
ck E3 "single bloat-removal owner (emoji, REST, embeds)"   'grep -q "function bamero_disable_bloat" "$THEME/inc/performance.php"'
ck E4 "exactly one wp_revisions_to_keep owner"   '[ "$(grep -rE "add_filter\(.wp_revisions_to_keep." "$THEME" --include="*.php" | wc -l)" -eq 1 ]'
ck E5 "autosave interval capped at 120s"   'grep -q "define(.AUTOSAVE_INTERVAL., 120);" "$THEME/inc/performance.php"'
ck E6 "main.js is vanilla-first with a jQuery gate"   'head -c 600 "$THEME/js/main.js" | grep -q "vanilla-first" && grep -qF "if (window.jQuery)" "$THEME/js/main.js"'
ck E7 "coverage calculator Persian strings preserved"   'grep -qF "مساحت را وارد کنید." "$THEME/js/main.js" && grep -qF "پوشش‌دهی محصول ثبت نشده است." "$THEME/js/main.js"'

echo
echo "[F] Governance and documentation"
ck F1 "go-live document family present"   'for d in GO_LIVE_VERIFICATION_REPORT.md GO_LIVE_RUNBOOK_FA.md GO_LIVE_PHP_HOSTING_FA.md DEPLOY_QUICKSTART_FA.md FAST_TRACK_DEPLOY_FA.md UI_UX_GOLIVE_AUDIT_2026-10.md THEME_GOLIVE_HARDENING_2026-10.md; do [ -f "$ROOT/$d" ] || exit 1; done'
ck F2 "theme README present"   '[ -f "$THEME/README.md" ]'
ck F3 "make certify target wired"   'grep -q "^certify:" "$ROOT/Makefile"'

echo
echo "=================================================="
if [ "$FAIL" -eq 0 ]; then
    echo " GO-LIVE CERTIFICATION: PASS ($PASS/$((PASS+FAIL)) checks)"
    echo "=================================================="
    exit 0
fi
echo " GO-LIVE CERTIFICATION: FAIL ($FAIL failed of $((PASS+FAIL)) checks)"
echo "=================================================="
exit 1
