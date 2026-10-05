<?php
/**
 * Plugin Name: Bamero Custom Plugin
 * Plugin URI: https://github.com/Mojig62m/rang
 * Description: Creates Bamero content pages. Theme-owned storefront presentation and product fields remain available when this plugin is inactive.
 * Version: 1.1.0
 * Author: Bamero
 * Author URI: https://github.com/Mojig62m/rang
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bamero-custom-plugin
 */

defined('ABSPATH') || exit;

define('BAMERO_PLUGIN_VERSION', '1.1.0');

/** Create pages that use theme-provided presentation. */
function bamero_custom_plugin_create_pages() {
    $pages = array(
        'consultation' => array(
            'title'   => 'مشاوره رنگ',
            'content' => '[bamero_color_consultation]',
        ),
        'about'        => array(
            'title'    => 'درباره ما',
            'content'  => '<p>بامرو با سال‌ها تجربه در زمینه فروش رنگ و محصولات ساختمانی، آماده ارائه بهترین خدمات به شما مشتریان عزیز است.</p>',
            'template' => 'about.php',
        ),
        'contact'      => array(
            'title'    => 'تماس با ما',
            'content'  => '',
            'template' => 'contact.php',
        ),
    );

    foreach ($pages as $slug => $page) {
        if (get_page_by_path($slug)) {
            continue;
        }

        $page_id = wp_insert_post(array(
            'post_title'   => $page['title'],
            'post_content' => $page['content'],
            'post_name'    => $slug,
            'post_type'    => 'page',
            'post_status'  => 'publish',
        ));

        if ($page_id && !is_wp_error($page_id) && !empty($page['template'])) {
            update_post_meta($page_id, '_wp_page_template', $page['template']);
        }
    }
}

function bamero_custom_plugin_activate() {
    bamero_custom_plugin_create_pages();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bamero_custom_plugin_activate');

function bamero_custom_plugin_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'bamero_custom_plugin_deactivate');