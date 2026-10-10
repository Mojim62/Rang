<?php
/**
 * Bamero Theme — Performance
 *
 * Core Web Vitals (LCP/INP/CLS): head bloat and emoji removal, defer of
 * the main script, heartbeat control, revision/autosave limits and lazy
 * iframes.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

// ===== PERFORMANCE IMPROVEMENTS =====

// Defer JavaScript loading
function bamero_defer_scripts($tag, $handle, $src) {
    // JS-01/02: defer only theme main script — never core library handles
    if ($handle === 'bamero-script') {
        return '<script type="text/javascript" src="' . esc_url($src) . '" defer></script>' . "
";
    }
    return $tag;
}
add_filter('script_loader_tag', 'bamero_defer_scripts', 10, 3);

// Disable heartbeats in admin
function bamero_disable_heartbeat() {
    if (is_admin()) {
        return;
    }
    wp_deregister_script('heartbeat');
}
add_action('init', 'bamero_disable_heartbeat', 1);

// =============================================================================
// PERFORMANCE (Core Web Vitals 2026: LCP, INP, CLS)
// =============================================================================

/** Remove emoji, embed, and other bloat */
function bamero_disable_bloat() {
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rest_output_link_wp_head');
    // Emoji bloat — single canonical owner (deduplicated from the former bamero_disable_emojis).
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    add_filter('emoji_svg_url', '__return_false');
}
add_action('init', 'bamero_disable_bloat', 1);

/** Fetchpriority on LCP candidate (logo / product image) */
function bamero_fetchpriority_logo($attr, $attachment, $size) {
    if (is_admin()) {
        return $attr;
    }
    // First content image often LCP on product pages
    if (!empty($attr['class']) && strpos($attr['class'], 'wp-post-image') !== false && is_product()) {
        $attr['fetchpriority'] = 'high';
        $attr['decoding'] = 'async';
    }
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'bamero_fetchpriority_logo', 10, 3);

/** Lazy-load iframes by default */
add_filter('wp_iframe_tag_add_loading_attr', function () {
    return 'lazy';
});

// =============================================================================
// REVISIONS / AUTOSAVE
// =============================================================================

/**
 * Limit post revisions: products are meta-heavy in WooCommerce (3),
 * everything else is capped at 5. Consolidates the two previous
 * wp_revisions_to_keep callbacks into a single owner.
 */
function bamero_limit_post_revisions($num, $post) {
    if (isset($post->post_type) && $post->post_type === 'product') {
        return 3;
    }
    return is_numeric($num) ? min(5, (int) $num) : 5;
}
add_filter('wp_revisions_to_keep', 'bamero_limit_post_revisions', 10, 2);

/** Prevent excessive autosave interval load */
if (!defined('AUTOSAVE_INTERVAL')) {
    define('AUTOSAVE_INTERVAL', 120);
}
