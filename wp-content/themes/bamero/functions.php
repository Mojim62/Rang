<?php
/**
 * Bamero Theme Functions — bootstrap.
 *
 * All logic lives in focused modules under inc/:
 *   helpers.php     — pure helpers (digits, price formatting, contact details)
 *   setup.php       — theme supports, enqueues, body classes, skip link
 *   woocommerce.php — WooCommerce integration (fields, tabs, fragments, search)
 *   seo.php         — structured data, meta robots, robots.txt
 *   performance.php — Core Web Vitals, bloat removal, revisions/autosave
 *   security.php    — CSP nonces, security headers, hardening
 *   forms.php       — front-end form handlers, Customizer settings
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

define('BAMERO_VERSION', '2.2.0');
define('BAMERO_THEME_DIR', get_template_directory_uri());
define('BAMERO_THEME_PATH', get_template_directory());

// Security: Hide WordPress version
remove_action('wp_head', 'wp_generator');

require_once BAMERO_THEME_PATH . '/inc/helpers.php';
require_once BAMERO_THEME_PATH . '/inc/setup.php';
require_once BAMERO_THEME_PATH . '/inc/woocommerce.php';
require_once BAMERO_THEME_PATH . '/inc/seo.php';
require_once BAMERO_THEME_PATH . '/inc/performance.php';
require_once BAMERO_THEME_PATH . '/inc/security.php';
require_once BAMERO_THEME_PATH . '/inc/forms.php';
