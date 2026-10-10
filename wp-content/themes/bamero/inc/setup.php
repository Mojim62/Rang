<?php
/**
 * Bamero Theme — Setup
 *
 * Theme supports, styles/script enqueues (conditional commerce
 * payloads), body classes and the accessibility skip link.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

// ===== THEME SETUP =====
function bamero_setup() {
    // Text domain for translations
    load_theme_textdomain('bamero', BAMERO_THEME_PATH . '/languages');

    // Title tag support
    add_theme_support('title-tag');

    // Custom logo support
    add_theme_support('custom-logo', array(
        'height' => 100,
        'width' => 400,
        'flex-height' => true,
        'flex-width' => true,
    ));

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('منوی اصلی', 'bamero'),
        'footer' => __('منوی پاورقی', 'bamero'),
    ));

    // Post thumbnail support
    add_theme_support('post-thumbnails');

    // Custom image sizes
    add_image_size('bamero-thumbnail', 300, 300, true);
    add_image_size('bamero-medium', 600, 400, true);
    add_image_size('bamero-large', 1200, 800, true);

    // RTL support
    add_theme_support('rtl');

    // WooCommerce support
    add_theme_support('woocommerce');

    // Native product gallery: lightbox (PhotoSwipe), zoom and thumbnail slider.
    // WooCommerce only prints the gallery trigger and enqueues its own
    // gallery scripts when these supports are declared (standard for WC themes).
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    // HTML5 support
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));

    // Automatic feed links
    add_theme_support('automatic-feed-links');

    // Custom header support
    add_theme_support('custom-header', array(
        'default-image' => '',
        'width' => 1920,
        'height' => 600,
        'flex-height' => true,
    ));

    // Custom background support
    add_theme_support('custom-background', array(
        'default-color' => '#f5f5f5',
    ));
}
add_action('after_setup_theme', 'bamero_setup');

// ===== ENQUEUE STYLES AND SCRIPTS =====
/**
 * Enqueue design tokens, stylesheets and the main script.
 *
 * The WooCommerce stylesheets load only on commerce surfaces (shop, product,
 * taxonomy, cart, checkout, account) and on search — the product grid renders
 * there as well. Every other page skips that CSS payload. The Modern Commerce
 * 2026 refresh was merged into storefront-modern.css (single file, one enqueue).
 */
function bamero_scripts() {
    // Design tokens first
    wp_enqueue_style(
        'bamero-variables',
        BAMERO_THEME_DIR . '/css/variables.css',
        array(),
        BAMERO_VERSION
    );

    wp_enqueue_style(
        'bamero-icons',
        BAMERO_THEME_DIR . '/css/icons.css',
        array('bamero-variables'),
        BAMERO_VERSION
    );

    wp_enqueue_style(
        'bamero-components-production',
        BAMERO_THEME_DIR . '/css/components-production.css',
        array('bamero-variables'),
        BAMERO_VERSION
    );

    // Main stylesheet
    wp_enqueue_style(
        'bamero-style',
        get_stylesheet_uri(),
        array('bamero-variables'),
        BAMERO_VERSION
    );

    // WooCommerce styles — commerce surfaces only (conditional enqueue).
    $bamero_is_commerce = (function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) || is_search();

    $bamero_storefront_dep = array('bamero-style');
    if ($bamero_is_commerce) {
        wp_enqueue_style(
            'bamero-woocommerce',
            BAMERO_THEME_DIR . '/css/woocommerce.css',
            array('bamero-style'),
            BAMERO_VERSION
        );

        // RTL WooCommerce styles (always, on the same surfaces as above).
        wp_enqueue_style(
            'bamero-woocommerce-rtl',
            BAMERO_THEME_DIR . '/css/woocommerce-rtl.css',
            array('bamero-woocommerce'),
            BAMERO_VERSION
        );

        $bamero_storefront_dep = array('bamero-woocommerce-rtl');
    }

    // Modern storefront layer (Modern Commerce 2026 refresh merged in).
    wp_enqueue_style(
        'bamero-storefront-modern',
        BAMERO_THEME_DIR . '/css/storefront-modern.css',
        $bamero_storefront_dep,
        BAMERO_VERSION
    );

    // Main script — vanilla core (drawer, quantity guard, coverage calculator).
    // jQuery becomes a dependency only on commerce surfaces, where WooCommerce loads it.
    $bamero_script_deps = $bamero_is_commerce ? array('jquery') : array();
    wp_enqueue_script(
        'bamero-script',
        BAMERO_THEME_DIR . '/js/main.js',
        $bamero_script_deps,
        BAMERO_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'bamero_scripts');

function bamero_preload_primary_font() {
    if (is_front_page() || is_product()) {
        $font_path = BAMERO_THEME_PATH . '/assets/fonts/Vazirmatn-wght.woff2';
        if (is_readable($font_path)) {
            printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>\n', esc_url(BAMERO_THEME_DIR . '/assets/fonts/Vazirmatn-wght.woff2'));
        }
    }
}
add_action('wp_head', 'bamero_preload_primary_font', 1);

/** JS-05: cart script only on commerce surfaces */
function bamero_maybe_enqueue_add_to_cart() {
    if (!function_exists('is_woocommerce')) {
        return;
    }
    if (is_shop() || is_product() || is_cart() || is_checkout() || is_product_category() || is_product_tag()) {
        wp_enqueue_script('wc-add-to-cart');
        // Live cart-count badge on every commerce surface: WooCommerce's own
        // fragments request refreshes span.cart-count (GATE-03 contract).
        wp_enqueue_script('wc-cart-fragments');
    }
}
add_action('wp_enqueue_scripts', 'bamero_maybe_enqueue_add_to_cart', 20);


// Add body classes
function bamero_body_classes($classes) {
    $classes[] = 'rtl';
    if (is_woocommerce()) {
        $classes[] = 'woocommerce-page';
    }
    return $classes;
}
add_filter('body_class', 'bamero_body_classes');

// ===== ACCESSIBILITY IMPROVEMENTS =====

// Add skip to content link
function bamero_skip_to_content_link() {
    echo '<a href="#main-content" class="skip-to-content screen-reader-text">' . esc_html__('پرش به محتوا', 'bamero') . '</a>';
}
add_action('wp_body_open', 'bamero_skip_to_content_link');

// Navigation accessibility is handled via aria-label on <nav> in header.php
// Do not mutate menu item titles (breaks screen readers and SEO).

