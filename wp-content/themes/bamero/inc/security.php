<?php
/**
 * Bamero Theme — Security
 *
 * Hardening: version disclosure removal, XML-RPC off, CSP nonces,
 * security headers and author-enumeration protection. bamero_csp_nonce()
 * and bamero_security_headers() stay function_exists()-guarded because the
 * canonical definitions live in the bamero-production-core plugin.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

// ===== SECURITY IMPROVEMENTS =====

// Remove WordPress version from RSS feeds
function bamero_remove_wp_version_rss() {
    return '';
}
add_filter('the_generator', 'bamero_remove_wp_version_rss');

// Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// =============================================================================
// SECURITY HEADERS (sent via PHP when server allows)
// =============================================================================

/**
 * CSP nonce (SEC-06). Generated once per request.
 *
 * Guarded with function_exists() because the canonical definition lives in the
 * bamero-production-core plugin, which WordPress loads BEFORE the theme. Without
 * this guard PHP aborts with a fatal "Cannot redeclare" error (see audit report).
 */
if ( ! function_exists( 'bamero_csp_nonce' ) ) {
    function bamero_csp_nonce() {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        return $nonce;
    }
}

if ( ! function_exists( 'bamero_security_headers' ) ) {
function bamero_security_headers() {
    if (headers_sent() || is_admin()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    $n = bamero_csp_nonce();
    // CSP-L3 style: no unsafe-inline for scripts; scripts need nonce.
    // style-src-attr allows the few dynamic inline style attributes (color swatches)
    // without opening up <style>/<link> to unsafe-inline.
    $csp = "default-src 'self'; "
        . "script-src 'self' 'nonce-{$n}'; "
        . "style-src 'self'; "
        . "style-src-attr 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data: https:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'";
    header('Content-Security-Policy: ' . $csp);
}
} // end if ( ! function_exists( 'bamero_security_headers' ) )
add_action('send_headers', 'bamero_security_headers');

function bamero_script_loader_nonce($tag, $handle, $src) {
    if (is_admin()) {
        return $tag;
    }
    $nonce = esc_attr(bamero_csp_nonce());
    if (strpos($tag, ' nonce=') === false) {
        $tag = str_replace('<script ', '<script nonce="' . $nonce . '" ', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'bamero_script_loader_nonce', 20, 3);

/** Add CSP nonce to WordPress-generated inline scripts (WP 6.4+). */
function bamero_inline_script_nonce($attributes) {
    if (is_admin()) {
        return $attributes;
    }
    if (empty($attributes['nonce'])) {
        $attributes['nonce'] = bamero_csp_nonce();
    }
    return $attributes;
}
add_filter('wp_inline_script_attributes', 'bamero_inline_script_nonce');

/** Disable author enumeration */
function bamero_disable_author_enum($redirect, $request) {
    if (preg_match('/\?author=([0-9]*)/i', $request)) {
        return home_url('/');
    }
    return $redirect;
}
add_filter('redirect_canonical', 'bamero_disable_author_enum', 10, 2);

