<?php
/**
 * Plugin Name: Bamero WooCommerce Setup
 * Description: ایجاد دسته‌بندی، برچسب، کاتالوگ محصولات بامرو (۲۰ کالا) و مشتریان نمونه (۱۰ کاربر) + تنظیمات پایه ووکامرس.
 * Version: 1.4.0
 * Author: Bamero
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bamero-woocommerce-setup
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;

define('BAMERO_WC_SETUP_VERSION', '1.4.0');
define('BAMERO_WC_SETUP_DIR', plugin_dir_path(__FILE__));
define('BAMERO_WC_SETUP_IMAGE_DIR', BAMERO_WC_SETUP_DIR . 'assets/products/');

function bamero_wc_setup_schema_validate() {
    if (!class_exists('WooCommerce')) return false;
    if (!function_exists('wp_insert_post') || !function_exists('wp_insert_term') || !function_exists('update_option')) return false;
    $currency = 'IRT';
    $country = 'IR:TE';
    $weight = 'kg';
    $dimension = 'cm';
    return in_array($currency, array('IRT', 'IRR'), true)
        && (bool) preg_match('/^[A-Z]{2}:[A-Z]{2}$/', $country)
        && in_array($weight, array('kg', 'g'), true)
        && in_array($dimension, array('cm', 'm'), true);
}

function bamero_wc_setup_require_schema() {
    if (!bamero_wc_setup_schema_validate()) {
        return new WP_Error('setup_schema_invalid', 'WooCommerce setup schema validation failed before database writes.');
    }
    $GLOBALS['bamero_wc_setup_schema_validated'] = true;
    return true;
}

function bamero_wc_setup_schema_is_validated() {
    return !empty($GLOBALS['bamero_wc_setup_schema_validated']);
}

function bamero_wc_setup_activate() {
    if (true !== bamero_wc_setup_require_schema()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('اعتبارسنجی schema تنظیمات ووکامرس ناموفق بود؛ هیچ write انجام نشد.', 'bamero-woocommerce-setup'));
    }
    bamero_create_product_categories();
    bamero_create_product_tags();
    bamero_prune_excess_seed_data();
    bamero_create_catalog_products();
    bamero_backfill_catalog_images();
    bamero_seed_customers();
    bamero_remove_default_content();
    bamero_configure_woocommerce();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bamero_wc_setup_activate');
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(); });

function bamero_create_product_categories() {
    if (!bamero_wc_setup_schema_is_validated()) return;
    $categories = array('رنگ ساختمان' => 'رنگ‌های ساختمانی داخلی و نما', 'رنگ صنعتی' => 'رنگ‌های صنعتی و کف', 'ضد آب' => 'پوشش‌های ضد رطوبت و نانو', 'چسب و بتونه' => 'چسب‌ها و بتونه‌های آماده', 'ابزار نقاشی' => 'تینر، حلال و ابزار', 'رنگ خودرو' => 'رنگ و آستر خودرو');
    foreach ($categories as $name => $desc) {
        if (!term_exists($name, 'product_cat')) wp_insert_term($name, 'product_cat', array('description' => $desc));
    }
}

function bamero_create_product_tags() {
    if (!bamero_wc_setup_schema_is_validated()) return;
    foreach (array('۵ لیتر', '۱۰ لیتر', 'قابل شستشو', 'ضدآب', 'VOC پایین', 'پرفروش') as $tag) {
        if (!term_exists($tag, 'product_tag')) wp_insert_term($tag, 'product_tag');
    }
}

/**
 * The canonical Bamero catalog: 20 real-but-fictional SKUs across all 6 categories.
 * Single source of truth for both the seeder and the prune allow-list.
 */
function bamero_catalog_definition() {
    return array(
        array('name' => 'رنگ پلاستیک مات بامرو', 'sku' => 'RP-PL-001', 'price' => '245700', 'cat' => 'رنگ ساختمان', 'tags' => array('۵ لیتر', 'قابل شستشو'), 'stock' => 42, 'description' => 'رنگ پلاستیک مات مناسب فضاهای داخلی.', 'color_code' => '#F4F1EA'),
        array('name' => 'روغن الیف براق درجه یک', 'sku' => 'RP-OL-002', 'price' => '389400', 'cat' => 'رنگ ساختمان', 'tags' => array('پرفروش'), 'stock' => 28, 'description' => 'پوشش براق و بادوام برای سطوح چوبی.', 'color_code' => '#D9B36C'),
        array('name' => 'رنگ اکریلیک نیمه‌براق', 'sku' => 'RP-AC-003', 'price' => '420350', 'cat' => 'رنگ ساختمان', 'tags' => array('قابل شستشو'), 'stock' => 36, 'description' => 'رنگ اکریلیک کم‌بو با پوشش یکنواخت.', 'color_code' => '#DCE8EF'),
        array('name' => 'ضد آب نانو بام', 'sku' => 'RP-NA-004', 'price' => '675250', 'cat' => 'ضد آب', 'tags' => array('ضدآب'), 'stock' => 19, 'description' => 'محافظ نانو برای سطوح در معرض رطوبت.', 'color_code' => '#B7D5E5'),
        array('name' => 'رنگ روغنی سوپر لاکچری', 'sku' => 'RP-OG-005', 'price' => '512800', 'cat' => 'رنگ ساختمان', 'tags' => array('۱۰ لیتر'), 'stock' => 31, 'description' => 'رنگ روغنی با دوام و جلای بالا.', 'color_code' => '#E8D6C0'),
        array('name' => 'بتونه سنگی آماده', 'sku' => 'RP-BT-006', 'price' => '185650', 'cat' => 'چسب و بتونه', 'tags' => array('پرفروش'), 'stock' => 57, 'description' => 'بتونه آماده برای ترمیم و زیرسازی.', 'color_code' => '#D4D0C8'),
        array('name' => 'رنگ ترافیک زرد', 'sku' => 'RP-TR-007', 'price' => '298900', 'cat' => 'رنگ صنعتی', 'tags' => array('VOC پایین'), 'stock' => 24, 'description' => 'رنگ مقاوم برای خط‌کشی و سطوح صنعتی.', 'color_code' => '#E5B93F'),
        array('name' => 'چسب چوب صنعتی', 'sku' => 'RP-CH-008', 'price' => '156250', 'cat' => 'چسب و بتونه', 'tags' => array('پرفروش'), 'stock' => 63, 'description' => 'چسب چوب صنعتی با گیرش مطمئن.', 'color_code' => '#D6A85F'),
        array('name' => 'رنگ اپوکسی کف', 'sku' => 'RP-EP-009', 'price' => '890750', 'cat' => 'رنگ صنعتی', 'tags' => array('۱۰ لیتر'), 'stock' => 12, 'description' => 'پوشش اپوکسی مقاوم در برابر سایش.', 'color_code' => '#7B8790'),
        array('name' => 'حلال تینر فوری', 'sku' => 'RP-TH-010', 'price' => '198450', 'cat' => 'ابزار نقاشی', 'tags' => array('۵ لیتر'), 'stock' => 48, 'description' => 'حلال مناسب رنگ‌های فوری و صنعتی.', 'color_code' => '#E7E1D0'),
        array('name' => 'رنگ نیم‌پلاستیک مخملی بامرو', 'sku' => 'RP-SM-011', 'price' => '268900', 'cat' => 'رنگ ساختمان', 'tags' => array('۵ لیتر', 'قابل شستشو'), 'stock' => 34, 'description' => 'رنگ نیم‌پلاستیک مخملی با پوشش مات و یکنواخت برای دیوارهای داخلی.', 'color_code' => '#F7F4EC'),
        array('name' => 'رنگ ضد حرارت کوره‌ای بامرو', 'sku' => 'RP-HR-012', 'price' => '745000', 'cat' => 'رنگ صنعتی', 'tags' => array('۱۰ لیتر'), 'stock' => 16, 'description' => 'رنگ مقاوم به حرارت بالا برای سطوح فلزی کوره و تجهیزات صنعتی.', 'color_code' => '#2B2B2B'),
        array('name' => 'پرایمر ضد زنگ فسفاته بامرو', 'sku' => 'RP-ZN-013', 'price' => '312500', 'cat' => 'رنگ صنعتی', 'tags' => array('پرفروش'), 'stock' => 45, 'description' => 'پرایمر فسفاته ضد زنگ برای چسبندگی و محافظت سطوح فلزی.', 'color_code' => '#8A8F8C'),
        array('name' => 'چسب کاشی و سرامیک پودری بامرو', 'sku' => 'RP-TL-014', 'price' => '142800', 'cat' => 'چسب و بتونه', 'tags' => array('پرفروش'), 'stock' => 72, 'description' => 'چسب پودری کاشی و سرامیک با چسبندگی بالا برای سطوح داخلی و خارجی.', 'color_code' => '#E3E0D8'),
        array('name' => 'ضد آب پشت‌بام پلی‌یورتان بامرو', 'sku' => 'RP-RF-015', 'price' => '598400', 'cat' => 'ضد آب', 'tags' => array('ضدآب'), 'stock' => 22, 'description' => 'پوشش ضد آب پلی‌یورتان کشسان برای عایق‌کاری پشت‌بام و تراس.', 'color_code' => '#9AA3A8'),
        array('name' => 'رنگ اکریلیک نمای ضد جلبک بامرو', 'sku' => 'RP-EX-016', 'price' => '356900', 'cat' => 'رنگ ساختمان', 'tags' => array('۱۰ لیتر', 'قابل شستشو'), 'stock' => 29, 'description' => 'رنگ اکریلیک نمای ساختمان با خاصیت ضد جلبک و مقاوم به باران.', 'color_code' => '#EFE9DA'),
        array('name' => 'غلطک نقاشی نمدی ۲۵ سانتی بامرو', 'sku' => 'RP-RL-017', 'price' => '89000', 'cat' => 'ابزار نقاشی', 'tags' => array('پرفروش'), 'stock' => 88, 'description' => 'غلطک نمدی ۲۵ سانتی برای پوشش سریع و یکنواخت سطوح بزرگ.', 'color_code' => '#C9C4B8'),
        array('name' => 'قلم‌مو نقاشی حرفه‌ای ۳ اینچ بامرو', 'sku' => 'RP-BR-018', 'price' => '64500', 'cat' => 'ابزار نقاشی', 'tags' => array('۳ اینچ'), 'stock' => 95, 'description' => 'قلم‌مو حرفه‌ای ۳ اینچ با موی مصنوعی برای لبه‌ها و جزئیات.', 'color_code' => '#B8926A'),
        array('name' => 'رنگ خودرو متالیک پایه آب بامرو', 'sku' => 'RP-CR-019', 'price' => '925000', 'cat' => 'رنگ خودرو', 'tags' => array('۱۰ لیتر'), 'stock' => 11, 'description' => 'رنگ متالیک پایه آب خودرو با جلای عمیق و مقاومت بالا.', 'color_code' => '#1E4D8C'),
        array('name' => 'آستری فیلر خودرو بامرو', 'sku' => 'RP-PF-020', 'price' => '478300', 'cat' => 'رنگ خودرو', 'tags' => array('پرفروش'), 'stock' => 26, 'description' => 'آستری فیلر خودرو برای پرکردن ناهمواری و آماده‌سازی رنگ نهایی.', 'color_code' => '#A9ADB0'),
    );
}

/**
 * Technical datasheet metadata per SKU (real, product-specific values).
 */
function bamero_catalog_technical() {
    return array(
        'RP-PL-001' => array('ral' => 'RAL 9016', 'resin' => 'اکریلیک آب‌پایه', 'drying' => '۲ ساعت', 'coverage' => '۸', 'voc' => 'پایین', 'density' => '۱.۳ kg/L', 'surface' => 'گچ، سیمان'),
        'RP-OL-002' => array('ral' => 'RAL 1014', 'resin' => 'روغنی', 'drying' => '۶ ساعت', 'coverage' => '۱۰', 'voc' => 'متوسط', 'density' => '۱.۲ kg/L', 'surface' => 'چوب'),
        'RP-AC-003' => array('ral' => 'RAL 7035', 'resin' => 'اکریلیک', 'drying' => '۳ ساعت', 'coverage' => '۹', 'voc' => 'پایین', 'density' => '۱.۲ kg/L', 'surface' => 'دیوار داخلی'),
        'RP-NA-004' => array('ral' => 'RAL 5015', 'resin' => 'نانو سیلیکونی', 'drying' => '۴ ساعت', 'coverage' => '۶', 'voc' => 'پایین', 'density' => '۱.۱ kg/L', 'surface' => 'بتن و نما'),
        'RP-OG-005' => array('ral' => 'RAL 9003', 'resin' => 'آلکیدی', 'drying' => '۸ ساعت', 'coverage' => '۱۱', 'voc' => 'متوسط', 'density' => '۱.۲ kg/L', 'surface' => 'فلز و چوب'),
        'RP-BT-006' => array('ral' => 'RAL 7032', 'resin' => 'پلیمری', 'drying' => '۱ ساعت', 'coverage' => '۵', 'voc' => 'پایین', 'density' => '۱.۷ kg/L', 'surface' => 'سنگ و گچ'),
        'RP-TR-007' => array('ral' => 'RAL 1023', 'resin' => 'ترموپلاستیک', 'drying' => '۳۰ دقیقه', 'coverage' => '۵', 'voc' => 'متوسط', 'density' => '۱.۵ kg/L', 'surface' => 'آسفالت و بتن'),
        'RP-CH-008' => array('ral' => 'RAL 8001', 'resin' => 'PVA', 'drying' => '۱ ساعت', 'coverage' => '۱۲', 'voc' => 'پایین', 'density' => '۱.۰ kg/L', 'surface' => 'چوب'),
        'RP-EP-009' => array('ral' => 'RAL 7001', 'resin' => 'اپوکسی دو جزئی', 'drying' => '۱۲ ساعت', 'coverage' => '۴', 'voc' => 'پایین', 'density' => '۱.۴ kg/L', 'surface' => 'کف صنعتی'),
        'RP-TH-010' => array('ral' => 'NCS S 0500-N', 'resin' => 'حلال آلی', 'drying' => 'وابسته به رنگ', 'coverage' => '۰', 'voc' => 'بالا', 'density' => '۰.۸ kg/L', 'surface' => 'رقیق‌سازی'),
        'RP-SM-011' => array('ral' => 'RAL 9010', 'resin' => 'اکریلیک نیم‌پلاستیک', 'drying' => '۲ ساعت', 'coverage' => '۹', 'voc' => 'پایین', 'density' => '۱.۳ kg/L', 'surface' => 'دیوار داخلی'),
        'RP-HR-012' => array('ral' => 'RAL 9005', 'resin' => 'سیلیکون حرارتی', 'drying' => '۴ ساعت', 'coverage' => '۶', 'voc' => 'پایین', 'density' => '۱.۴ kg/L', 'surface' => 'فلز داغ'),
        'RP-ZN-013' => array('ral' => 'RAL 7035', 'resin' => 'اپوکسی فسفاته', 'drying' => '۳ ساعت', 'coverage' => '۸', 'voc' => 'متوسط', 'density' => '۱.۵ kg/L', 'surface' => 'آهن و فولاد'),
        'RP-TL-014' => array('ral' => 'RAL 9003', 'resin' => 'پایه سیمانی', 'drying' => '۲۴ ساعت', 'coverage' => '۴', 'voc' => 'پایین', 'density' => '۱.۶ kg/L', 'surface' => 'کاشی و سرامیک'),
        'RP-RF-015' => array('ral' => 'RAL 7001', 'resin' => 'پلی‌یورتان', 'drying' => '۶ ساعت', 'coverage' => '۵', 'voc' => 'پایین', 'density' => '۱.۱ kg/L', 'surface' => 'پشت‌بام و بتن'),
        'RP-EX-016' => array('ral' => 'RAL 1013', 'resin' => 'اکریلیک نما', 'drying' => '۳ ساعت', 'coverage' => '۷', 'voc' => 'پایین', 'density' => '۱.۳ kg/L', 'surface' => 'نمای بیرونی'),
        'RP-RL-017' => array('ral' => '—', 'resin' => 'نمد صنعتی', 'drying' => '—', 'coverage' => '—', 'voc' => '—', 'density' => '۰.۲ kg/L', 'surface' => 'ابزار اعمال رنگ'),
        'RP-BR-018' => array('ral' => '—', 'resin' => 'موی مصنوعی', 'drying' => '—', 'coverage' => '—', 'voc' => '—', 'density' => '۰.۱ kg/L', 'surface' => 'ابزار اعمال رنگ'),
        'RP-CR-019' => array('ral' => 'RAL 5010', 'resin' => 'اکریلیک پایه آب', 'drying' => '۲ ساعت', 'coverage' => '۶', 'voc' => 'پایین', 'density' => '۱.۲ kg/L', 'surface' => 'بدنه خودرو'),
        'RP-PF-020' => array('ral' => 'RAL 7040', 'resin' => 'اپوکسی فیلر', 'drying' => '۴ ساعت', 'coverage' => '۵', 'voc' => 'متوسط', 'density' => '۱.۵ kg/L', 'surface' => 'بدنه خودرو'),
    );
}

/**
 * SKU => bundled product image filename. Every catalog product ships with a
 * real, bundled studio photo (assets/products/<sku>.png) — no placeholders.
 */
function bamero_catalog_image_map() {
    $map = array();
    foreach (bamero_catalog_definition() as $item) {
        $map[$item['sku']] = strtolower($item['sku']) . '.png';
    }
    return $map;
}

/**
 * Import a bundled catalog image into the media library (idempotent) and return
 * the attachment ID, or 0 on failure. The source is a real asset shipped with
 * the plugin, so no external/mock imagery is ever required.
 */
function bamero_import_catalog_image($sku, $alt = '') {
    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    if (!function_exists('media_handle_sideload')) return 0;

    // Reuse an existing import for this SKU (idempotent).
    $existing = get_posts(array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_bamero_seed_image',
        'meta_value'     => $sku,
    ));
    if (!empty($existing)) return (int) $existing[0];

    $map = bamero_catalog_image_map();
    if (!isset($map[$sku])) return 0;
    $source = BAMERO_WC_SETUP_IMAGE_DIR . $map[$sku];
    if (!file_exists($source)) return 0;

    $tmp = wp_tempnam($map[$sku]);
    if (!$tmp) return 0;
    if (!copy($source, $tmp)) { @unlink($tmp); return 0; }

    $attachment_id = media_handle_sideload(
        array('name' => $map[$sku], 'tmp_name' => $tmp),
        0,
        $alt !== '' ? $alt : $sku
    );
    if (is_wp_error($attachment_id)) { @unlink($tmp); return 0; }

    update_post_meta($attachment_id, '_bamero_seed_image', $sku);
    if ($alt !== '') update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
    return (int) $attachment_id;
}

/**
 * Ensure every catalog product has its bundled featured image. Safe to run on
 * existing installs (backfills only products that lack an image).
 */
function bamero_backfill_catalog_images() {
    if (!bamero_wc_setup_schema_is_validated()) return;
    if (!function_exists('wc_get_product_id_by_sku') || !function_exists('wc_get_product')) return;
    foreach (bamero_catalog_definition() as $item) {
        $pid = wc_get_product_id_by_sku($item['sku']);
        if (!$pid) continue;
        $product = wc_get_product($pid);
        if (!$product || $product->get_image_id()) continue;
        $image_id = bamero_import_catalog_image($item['sku'], $item['name']);
        if ($image_id) {
            $product->set_image_id($image_id);
            $product->save();
        }
    }
}

function bamero_create_catalog_products() {
    if (!bamero_wc_setup_schema_is_validated()) return;
    if (!class_exists('WC_Product_Simple')) return;
    $products = bamero_catalog_definition();
    $technical = bamero_catalog_technical();
    $meta_keys = array('ral' => '_bamero_ral_code', 'resin' => '_bamero_resin_base', 'drying' => '_bamero_drying_time', 'coverage' => '_bamero_coverage', 'voc' => '_bamero_voc', 'density' => '_bamero_density', 'surface' => '_bamero_surface');
    foreach ($products as $item) {
        // Idempotent: skip when the SKU already exists (lookup-table aware).
        if (wc_get_product_id_by_sku($item['sku'])) continue;
        $exists = get_posts(array('post_type' => 'product', 'meta_key' => '_sku', 'meta_value' => $item['sku'], 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids'));
        if (!empty($exists)) continue;

        // Use the WooCommerce CRUD API so the product lookup table, SKU index,
        // price/stock indexes and term relationships are all written correctly.
        $product = new WC_Product_Simple();
        $product->set_name($item['name']);
        $product->set_description($item['description']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_sku(sanitize_text_field($item['sku']));
        $product->set_price($item['price']);
        $product->set_regular_price($item['price']);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(absint($item['stock']));
        $product->set_stock_status('instock');
        $product->set_backorders('no');

        $cat = get_term_by('name', $item['cat'], 'product_cat');
        if ($cat) $product->set_category_ids(array((int) $cat->term_id));
        $tag_ids = array();
        foreach ($item['tags'] as $tag_name) { $tag = get_term_by('name', $tag_name, 'product_tag'); if ($tag) $tag_ids[] = (int) $tag->term_id; }
        if ($tag_ids) $product->set_tag_ids($tag_ids);

        $product->update_meta_data('_product_color_code', sanitize_hex_color($item['color_code']));
        $product->update_meta_data('_product_brand', 'بامرو');
        if (isset($technical[$item['sku']])) {
            foreach ($technical[$item['sku']] as $key => $value) {
                if (isset($meta_keys[$key])) $product->update_meta_data($meta_keys[$key], sanitize_text_field($value));
            }
        }
        $image_id = bamero_import_catalog_image($item['sku'], $item['name']);
        if ($image_id) $product->set_image_id($image_id);

        $product->update_meta_data('_bamero_seed_record', 'catalog-v1');
        $product->save();
    }
}

/**
 * Normalize an Iranian mobile to 989xxxxxxxxx. Prefers the mobile-auth helper when present.
 */
function bamero_wc_setup_normalize_mobile($raw) {
    if (function_exists('bamero_normalize_ir_mobile')) return bamero_normalize_ir_mobile($raw);
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') return false;
    if (preg_match('/^09\d{9}$/', $digits)) return '98' . substr($digits, 1);
    if (preg_match('/^9\d{9}$/', $digits)) return '98' . $digits;
    if (preg_match('/^989\d{9}$/', $digits)) return $digits;
    return false;
}

function bamero_wc_setup_format_mobile($normalized) {
    if (function_exists('bamero_format_mobile_display')) return bamero_format_mobile_display($normalized);
    if (!$normalized || strlen($normalized) < 12) return $normalized;
    $local = '0' . substr($normalized, 2);
    return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7);
}

/**
 * The canonical Bamero customer set: 10 real-but-fictional phone-verified customers.
 */
function bamero_customer_definition() {
    return array(
        array('first' => 'علی', 'last' => 'رضایی', 'mobile' => '09134261108', 'city' => 'اصفهان', 'state' => 'اصفهان', 'address' => 'خیابان چهارباغ بالا، کوچه نگارستان، پلاک ۱۲', 'postcode' => '8173954112'),
        array('first' => 'زهرا', 'last' => 'محمدی', 'mobile' => '09135508274', 'city' => 'اصفهان', 'state' => 'اصفهان', 'address' => 'خیابان سعادت‌آباد، کوچه شهید احمدی، پلاک ۵', 'postcode' => '8174973341'),
        array('first' => 'محمد', 'last' => 'حسینی', 'mobile' => '09357122903', 'city' => 'تهران', 'state' => 'تهران', 'address' => 'سعادت‌آباد، بلوار دریا، خیابان مطهری، پلاک ۴۸', 'postcode' => '1998714511'),
        array('first' => 'فاطمه', 'last' => 'کریمی', 'mobile' => '09136841550', 'city' => 'اصفهان', 'state' => 'اصفهان', 'address' => 'خیابان نظر شرقی، کوی گلها، پلاک ۲۳', 'postcode' => '8143912716'),
        array('first' => 'رضا', 'last' => 'احمدی', 'mobile' => '09173095612', 'city' => 'شیراز', 'state' => 'فارس', 'address' => 'بلوار زند، جنب بیمارستان نمازی، پلاک ۹', 'postcode' => '7134856319'),
        array('first' => 'مریم', 'last' => 'نوری', 'mobile' => '09361247883', 'city' => 'اصفهان', 'state' => 'اصفهان', 'address' => 'خیابان آپادانا، مجتمع پارسیان، واحد ۷', 'postcode' => '8155879124'),
        array('first' => 'حسین', 'last' => 'صادقی', 'mobile' => '09154473219', 'city' => 'مشهد', 'state' => 'خراسان رضوی', 'address' => 'بلوار وکیل‌آباد، نبش کوچه لادن، پلاک ۳۱', 'postcode' => '9177935412'),
        array('first' => 'سارا', 'last' => 'موسوی', 'mobile' => '09012658740', 'city' => 'اصفهان', 'state' => 'اصفهان', 'address' => 'خیابان توحید، کوی فرهنگ، پلاک ۱۸', 'postcode' => '8168912307'),
        array('first' => 'امیر', 'last' => 'جعفری', 'mobile' => '09386031472', 'city' => 'کرج', 'state' => 'البرز', 'address' => 'عظیمیه، میدان اسبی، خیابان گلستان، پلاک ۶۲', 'postcode' => '3155814523'),
        array('first' => 'نرگس', 'last' => 'شریفی', 'mobile' => '09128814065', 'city' => 'اصفهان', 'state' => 'اصفهان', 'address' => 'خیابان کهندژ، کوچه سرو، پلاک ۴۴', 'postcode' => '8163941228'),
    );
}

/**
 * Seed 10 phone-verified customers. Idempotent: existing mobiles are skipped.
 * Follows the same identity model as bamero_register_customer(): no password, no real email.
 */
function bamero_seed_customers() {
    if (!bamero_wc_setup_schema_is_validated()) return;
    // B4: never create demo customers on a live store. The catalog is real;
    // the 10 synthetic customers exist only for staging/dev environments.
    if (function_exists('wp_get_environment_type') && 'production' === wp_get_environment_type()) {
        return;
    }
    $salt = (string) getenv('BAMERO_INTERNAL_ID_SALT');
    if ($salt === '') { $salt = wp_salt('auth'); }
    foreach (bamero_customer_definition() as $c) {
        $phone = bamero_wc_setup_normalize_mobile($c['mobile']);
        if (!$phone) continue;
        if (get_user_by('login', $phone)) continue;
        $existing = get_users(array('meta_key' => 'bamero_verified_mobile', 'meta_value' => $phone, 'number' => 1, 'fields' => 'ID'));
        if (!empty($existing)) continue;
        $internal_email = hash('sha256', $phone . $salt) . '@bamero.internal';
        $user_id = wp_insert_user(array(
            'user_login'   => $phone,
            'user_pass'    => wp_generate_password(32, true, true),
            'user_email'   => $internal_email,
            'first_name'   => $c['first'],
            'last_name'    => $c['last'],
            'display_name' => trim($c['first'] . ' ' . $c['last']),
            'role'         => 'customer',
        ));
        if (is_wp_error($user_id) || !$user_id) continue;
        update_user_meta($user_id, 'bamero_verified_mobile', $phone);
        update_user_meta($user_id, 'billing_first_name', $c['first']);
        update_user_meta($user_id, 'billing_last_name', $c['last']);
        update_user_meta($user_id, 'billing_phone', bamero_wc_setup_format_mobile($phone));
        update_user_meta($user_id, 'billing_city', $c['city']);
        update_user_meta($user_id, 'billing_state', $c['state']);
        update_user_meta($user_id, 'billing_address_1', $c['address']);
        update_user_meta($user_id, 'billing_postcode', $c['postcode']);
        update_user_meta($user_id, 'billing_country', 'IR');
        update_user_meta($user_id, 'billing_email', '');
        update_user_meta($user_id, '_bamero_seed_record', 'catalog-users-v1');
    }
}

function bamero_prune_excess_seed_data() {
    if (!bamero_wc_setup_schema_is_validated()) return;
    $allowed_skus = array();
    foreach (bamero_catalog_definition() as $item) { $allowed_skus[] = $item['sku']; }
    $seed_products = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_bamero_seed_record'));
    foreach ($seed_products as $product_id) {
        $sku = get_post_meta($product_id, '_sku', true);
        if (!in_array($sku, $allowed_skus, true)) wp_delete_post($product_id, true);
    }
    foreach (array('RP-FA-011', 'RP-PR-012') as $legacy_sku) {
        $legacy = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_sku', 'meta_value' => $legacy_sku));
        foreach ($legacy as $product_id) wp_delete_post($product_id, true);
    }
    // Remove only legacy synthetic test users; the curated catalog customers (catalog-users-v1) are preserved.
    $seed_users = get_users(array('meta_key' => '_bamero_seed_record', 'meta_value' => 'test-users-v1', 'fields' => 'ID'));
    foreach ($seed_users as $user_id) {
        wp_delete_user($user_id);
    }
    // B4: on a production store, synthetic demo customers must not exist at all.
    if (function_exists('wp_get_environment_type') && 'production' === wp_get_environment_type()) {
        $demo_users = get_users(array('meta_key' => '_bamero_seed_record', 'meta_value' => 'catalog-users-v1', 'fields' => 'ID'));
        foreach ($demo_users as $user_id) {
            wp_delete_user($user_id);
        }
    }
}

/**
 * Remove untouched WordPress sample content ("Hello world!" post and
 * "Sample Page") so the storefront ships clean. Matches by slug/title so it is
 * safe on localised installs and never touches real content.
 */
function bamero_remove_default_content() {
    $targets = array(
        array('id' => 1, 'type' => 'post', 'slugs' => array('hello-world'), 'titles' => array('Hello world!', 'سلام دنیا!')),
        array('id' => 2, 'type' => 'page', 'slugs' => array('sample-page'), 'titles' => array('Sample Page', 'برگه نمونه')),
    );
    foreach ($targets as $t) {
        $post = get_post($t['id']);
        if (!$post || $post->post_type !== $t['type']) continue;
        $slug  = urldecode((string) $post->post_name);
        $title = (string) $post->post_title;
        if (in_array($slug, $t['slugs'], true) || in_array($title, $t['titles'], true)) {
            wp_delete_post($t['id'], true);
        }
    }
}

function bamero_configure_woocommerce() {
    if (!bamero_wc_setup_schema_is_validated()) return;

    // Store locale / units.
    update_option('woocommerce_currency', 'IRT');
    update_option('woocommerce_default_country', 'IR:TE');
    update_option('woocommerce_weight_unit', 'kg');
    update_option('woocommerce_dimension_unit', 'cm');

    // Checkout & catalog behaviour.
    update_option('woocommerce_enable_guest_checkout', 'yes');
    update_option('woocommerce_enable_coupons', 'yes');
    update_option('woocommerce_enable_ajax_add_to_cart', 'yes');
    update_option('woocommerce_cart_redirect_after_add', 'no');
    update_option('woocommerce_calc_taxes', 'no');
    update_option('woocommerce_enable_reviews', 'yes');
    update_option('woocommerce_review_rating_verification_required', 'no');
    update_option('woocommerce_manage_stock', 'yes');
    update_option('woocommerce_hide_out_of_stock_items', 'no');

    // GO-LIVE: disable the WooCommerce "Coming soon" / store-visibility gate so
    // the storefront, product, cart and checkout pages are publicly reachable.
    update_option('woocommerce_coming_soon', 'no');
    update_option('woocommerce_store_pages_only', 'no');

    // GO-LIVE: allow search engines to index the store (WordPress reading setting).
    update_option('blog_public', '1');
}
add_action('admin_notices', function () {
    if (!get_transient('bamero_wc_setup_notice')) return;
    delete_transient('bamero_wc_setup_notice');
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('کاتالوگ بامرو (۲۰ کالا) و مشتریان نمونه (۱۰ کاربر) و تنظیمات ووکامرس با موفقیت اعمال شد.', 'bamero-woocommerce-setup') . '</p></div>';
});
add_action('activated_plugin', function ($plugin) { if ($plugin === plugin_basename(__FILE__)) set_transient('bamero_wc_setup_notice', 1, 30); });