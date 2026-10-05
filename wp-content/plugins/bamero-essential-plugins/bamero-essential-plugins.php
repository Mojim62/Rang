<?php
/**
 * Plugin Name: Bamero Essential Plugins
 * Plugin URI: https://github.com/Mojig62m/rang
 * Description: Administrator-controlled installer for Bamero's WordPress.org plugin recommendations.
 * Version: 1.1.0
 * Author: Bamero
 * Author URI: https://github.com/Mojig62m/rang
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bamero-essential-plugins
 */

defined('ABSPATH') || exit;

function bamero_get_essential_plugins() {
    return array(
        array('name' => 'WooCommerce', 'slug' => 'woocommerce', 'file' => 'woocommerce.php', 'required' => true),
        array('name' => 'YITH WooCommerce Wishlist', 'slug' => 'yith-woocommerce-wishlist', 'file' => 'init.php', 'required' => false),
        array('name' => 'Autoptimize', 'slug' => 'autoptimize', 'file' => 'autoptimize.php', 'required' => false),
        array('name' => 'WP Super Cache', 'slug' => 'wp-super-cache', 'file' => 'wp-cache.php', 'required' => false),
        array('name' => 'Rank Math SEO', 'slug' => 'seo-by-rank-math', 'file' => 'rank-math.php', 'required' => false),
        array('name' => 'Wordfence Security', 'slug' => 'wordfence', 'file' => 'wordfence.php', 'required' => false),
        array('name' => 'Contact Form 7', 'slug' => 'contact-form-7', 'file' => 'wp-contact-form-7.php', 'required' => false),
    );
}

function bamero_essential_plugin_path($plugin) {
    return $plugin['slug'] . '/' . $plugin['file'];
}

function bamero_add_essential_plugins_page() {
    add_submenu_page('options-general.php', 'افزونه‌های ضروری بامرو', 'افزونه‌های ضروری', 'install_plugins', 'bamero-essential-plugins', 'bamero_essential_plugins_page_html');
}
add_action('admin_menu', 'bamero_add_essential_plugins_page');

function bamero_essential_plugins_page_html() {
    if (!current_user_can('install_plugins')) {
        wp_die(esc_html__('شما اجازه مدیریت افزونه‌ها را ندارید.', 'bamero-essential-plugins'), '', array('response' => 403));
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $plugins = bamero_get_essential_plugins();
    ?>
    <div class="wrap">
        <h1>افزونه‌های پیشنهادی بامرو</h1>
        <p>نصب هر افزونه به‌صورت جداگانه و پس از تأیید شما انجام می‌شود. درگاه زرین‌پال به دلیل نیاز به بررسی منبع و تنظیمات پذیرنده، عمداً در این فهرست نصب خودکار ندارد.</p>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>نام افزونه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($plugins as $plugin) : ?>
                <?php $path = bamero_essential_plugin_path($plugin); $installed = file_exists(WP_PLUGIN_DIR . '/' . $path); $active = $installed && is_plugin_active($path); ?>
                <tr>
                    <td><?php echo esc_html($plugin['name']); ?></td>
                    <td><?php echo esc_html($active ? 'فعال' : ($installed ? 'نصب شده' : 'نصب نشده')); ?></td>
                    <td>
                        <?php if (!$installed) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('bamero_install_plugin_' . $plugin['slug']); ?>
                                <input type="hidden" name="action" value="bamero_install_essential_plugin">
                                <input type="hidden" name="plugin" value="<?php echo esc_attr($plugin['slug']); ?>">
                                <button type="submit" class="button button-primary">نصب و فعال کردن</button>
                            </form>
                        <?php elseif (!$active) : ?>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . rawurlencode($path)), 'activate-plugin_' . $path)); ?>" class="button button-primary">فعال کردن</a>
                        <?php else : ?>
                            <span>فعال</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function bamero_install_essential_plugin() {
    if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
        wp_die(
            esc_html__('نصب افزونه غیرفعال است (DISALLOW_FILE_MODS=true). افزونه‌ها را در سطح میزبان نصب کنید.', 'bamero-essential-plugins'),
            '',
            array('response' => 403)
        );
    }
    if (!current_user_can('install_plugins')) {
        wp_die(esc_html__('شما اجازه نصب افزونه‌ها را ندارید.', 'bamero-essential-plugins'), '', array('response' => 403));
    }

    $slug = isset($_POST['plugin']) ? sanitize_key(wp_unslash($_POST['plugin'])) : '';
    check_admin_referer('bamero_install_plugin_' . $slug);
    $plugins = wp_list_pluck(bamero_get_essential_plugins(), null, 'slug');
    if (!isset($plugins[$slug])) {
        wp_die(esc_html__('افزونه در فهرست مجاز نیست.', 'bamero-essential-plugins'), '', array('response' => 400));
    }

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $api = plugins_api('plugin_information', array('slug' => $slug, 'fields' => array('sections' => false)));
    if (is_wp_error($api)) {
        wp_die(esc_html($api->get_error_message()), '', array('response' => 502));
    }

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    if (!$upgrader->install($api->download_link)) {
        wp_die(esc_html__('نصب افزونه ناموفق بود.', 'bamero-essential-plugins'), '', array('response' => 502));
    }

    $path = bamero_essential_plugin_path($plugins[$slug]);
    $result = activate_plugin($path);
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), '', array('response' => 502));
    }

    wp_safe_redirect(add_query_arg('bamero_plugin_installed', $slug, admin_url('options-general.php?page=bamero-essential-plugins')));
    exit;
}
add_action('admin_post_bamero_install_essential_plugin', 'bamero_install_essential_plugin');