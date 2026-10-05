<?php
/**
 * Bamero Theme Functions
 *
 * @package Bamero
 */

// Define theme constants


/**
 * Convert Western digits to Persian digits.
 */
function bamero_persian_digits($str) {
    $en = array('0','1','2','3','4','5','6','7','8','9');
    $fa = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
    return str_replace($en, $fa, (string) $str);
}

/**
 * Format product price with Persian digits + تومان.
 */
function bamero_format_price_html($product) {
    if (!is_object($product) || !method_exists($product, 'get_price')) {
        return '';
    }
    $price = $product->get_price();
    if ($price === '' || $price === null) {
        return '';
    }
    $formatted = number_format((float) $price, 0, '', ',');
    $unit = (function_exists('get_woocommerce_currency') && 'IRR' === get_woocommerce_currency()) ? ' ریال' : ' تومان';
    $html = '<span class="amount">' . esc_html(bamero_persian_digits($formatted)) . esc_html($unit) . '</span>';
    if ($product->is_on_sale() && $product->get_regular_price()) {
        $reg = number_format((float) $product->get_regular_price(), 0, '', ',');
        $html = '<del aria-hidden="true">' . esc_html(bamero_persian_digits($reg)) . '</del> ' . $html;
    }
    return $html;
}

define('BAMERO_VERSION', '2.0.0');
define('BAMERO_THEME_DIR', get_template_directory_uri());
define('BAMERO_THEME_PATH', get_template_directory());

// Security: Hide WordPress version
remove_action('wp_head', 'wp_generator');

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

    // WooCommerce styles
    wp_enqueue_style(
        'bamero-woocommerce',
        BAMERO_THEME_DIR . '/css/woocommerce.css',
        array('bamero-style'),
        BAMERO_VERSION
    );

    // RTL WooCommerce styles (always for this Persian theme)
    wp_enqueue_style(
        'bamero-woocommerce-rtl',
        BAMERO_THEME_DIR . '/css/woocommerce-rtl.css',
        array('bamero-woocommerce'),
        BAMERO_VERSION
    );

    wp_enqueue_style(
        'bamero-storefront-modern',
        BAMERO_THEME_DIR . '/css/storefront-modern.css',
        array('bamero-woocommerce-rtl'),
        BAMERO_VERSION
    );

    // Main script
    wp_enqueue_script(
        'bamero-script',
        BAMERO_THEME_DIR . '/js/main.js',
        array('jquery'),
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
        if (function_exists('is_cart') && (is_cart() || is_checkout())) {
            wp_enqueue_script('wc-cart-fragments');
        }
    }
}
add_action('wp_enqueue_scripts', 'bamero_maybe_enqueue_add_to_cart', 20);


// ===== WOOCOMMERCE CUSTOMIZATIONS =====

// Change WooCommerce texts to Persian
function bamero_woocommerce_persian_text($translated_text, $text, $domain) {
    if ($domain === 'woocommerce') {
        $translations = array(
            'Add to cart' => 'افزودن به سبد خرید',
            'Shop' => 'فروشگاه',
            'Cart' => 'سبد خرید',
            'Checkout' => 'تسویه حساب',
            'My Account' => 'حساب کاربری',
            'Related products' => 'محصولات مرتبط',
            'Description' => 'توضیحات',
            'Additional information' => 'اطلاعات بیشتر',
            'Reviews' => 'نظرات',
            'Search' => 'جستجو',
            'Categories' => 'دسته‌بندی‌ها',
            'Filter by price' => 'فیلتر بر اساس قیمت',
            'Price: Low to High' => 'قیمت: ارزان به گران',
            'Price: High to Low' => 'قیمت: گران به ارزان',
            'Sort by popularity' => 'مرتب‌سازی بر اساس محبوبیت',
            'Sort by average rating' => 'مرتب‌سازی بر اساس امتیاز',
            'Sort by newness' => 'مرتب‌سازی بر اساس جدیدترین',
            'Sort by price: low to high' => 'مرتب‌سازی بر اساس قیمت: ارزان به گران',
            'Sort by price: high to low' => 'مرتب‌سازی بر اساس قیمت: گران به ارزان',
            'In stock' => 'موجود',
            'Out of stock' => 'اتمام موجودی',
            'Add to wishlist' => 'افزودن به لیست علاقه‌مندی',
            'Remove from wishlist' => 'حذف از لیست علاقه‌مندی',
            'Your wishlist' => 'لیست علاقه‌مندی شما',
            'No products in the wishlist' => 'هیچ محصولی در لیست علاقه‌مندی وجود ندارد',
            'Proceed to checkout' => 'ادامه به تسویه حساب',
            'Update cart' => 'به‌روزرسانی سبد خرید',
            'Cart totals' => 'جمع سبد خرید',
            'Subtotal' => 'جمع جزئی',
            'Total' => 'جمع کل',
            'Shipping' => 'هزینه ارسال',
            'Free shipping' => 'ارسال رایگان',
            'Billing details' => 'جزئیات فاکتور',
            'Shipping details' => 'جزئیات ارسال',
            'Payment method' => 'روش پرداخت',
            'Order notes' => 'یادداشت‌های سفارش',
            'Place order' => 'ثبت سفارش',
            'Your order' => 'سفارش شما',
            'Product' => 'محصول',
            'Quantity' => 'تعداد',
            'Price' => 'قیمت',
            'Remove' => 'حذف',
            'Apply coupon' => 'اعمال کوپن',
            'Coupon code' => 'کد کوپن',
            'Enter your coupon code if you have one' => 'اگر کد کوپن دارید، وارد کنید',
            'Coupon has been applied successfully' => 'کوپن با موفقیت اعمال شد',
            'Sorry, this coupon does not exist' => 'متأسفانه این کوپن وجود ندارد',
            'Please enter a coupon code' => 'لطفاً کد کوپن وارد کنید',
        );

        if (isset($translations[$text])) {
            return $translations[$text];
        }
    }
    return $translated_text;
}
add_filter('gettext', 'bamero_woocommerce_persian_text', 20, 3);

// Add body classes
function bamero_body_classes($classes) {
    $classes[] = 'rtl';
    if (is_woocommerce()) {
        $classes[] = 'woocommerce-page';
    }
    return $classes;
}
add_filter('body_class', 'bamero_body_classes');

// Custom product fields
function bamero_add_custom_product_fields() {
    woocommerce_wp_text_input(array(
        'id' => '_product_color_code',
        'label' => 'کد رنگ',
        'placeholder' => 'مثال: #FFFFFF',
        'desc_tip' => true,
        'description' => 'کد رنگ محصول را وارد کنید.',
    ));

    woocommerce_wp_text_input(array(
        'id' => '_product_brand',
        'label' => 'برند',
        'placeholder' => 'مثال: بامرو',
        'desc_tip' => true,
        'description' => 'برند محصول را وارد کنید.',
    ));

    woocommerce_wp_textarea_input(array(
        'id' => '_product_additional_info',
        'label' => 'اطلاعات اضافی',
        'placeholder' => 'اطلاعات اضافی محصول را وارد کنید.',
        'desc_tip' => true,
        'description' => 'اطلاعات اضافی در مورد محصول.',
    ));

    foreach (array(
        '_bamero_ral_code' => array('label' => 'کد RAL/NCS', 'placeholder' => 'مثال: RAL 9016'),
        '_bamero_resin_base' => array('label' => 'پایه رزین', 'placeholder' => 'مثال: اکریلیک آب‌پایه'),
        '_bamero_drying_time' => array('label' => 'زمان خشک شدن', 'placeholder' => 'مثال: ۲ ساعت'),
        '_bamero_coverage' => array('label' => 'پوشش‌دهی (m²/L)', 'placeholder' => 'مثال: ۸'),
        '_bamero_voc' => array('label' => 'VOC', 'placeholder' => 'مثال: پایین'),
        '_bamero_density' => array('label' => 'دانسیته', 'placeholder' => 'مثال: ۱.۲ kg/L'),
        '_bamero_surface' => array('label' => 'سطح مناسب', 'placeholder' => 'مثال: دیوار، بتن'),
        '_bamero_msds_url' => array('label' => 'لینک MSDS', 'placeholder' => 'https://'),
    ) as $field_id => $field) {
        woocommerce_wp_text_input(array('id' => $field_id, 'label' => $field['label'], 'placeholder' => $field['placeholder'], 'desc_tip' => true));
    }
}
add_action('woocommerce_product_options_general_product_data', 'bamero_add_custom_product_fields');

// Save custom product fields
function bamero_save_custom_product_fields($post_id) {
    $product_color_code = isset($_POST['_product_color_code']) ? sanitize_hex_color(wp_unslash($_POST['_product_color_code'])) : '';
    $product_brand = isset($_POST['_product_brand']) ? sanitize_text_field(wp_unslash($_POST['_product_brand'])) : '';
    $product_additional_info = isset($_POST['_product_additional_info']) ? sanitize_textarea_field(wp_unslash($_POST['_product_additional_info'])) : '';

    update_post_meta($post_id, '_product_color_code', $product_color_code);
    update_post_meta($post_id, '_product_brand', $product_brand);
    update_post_meta($post_id, '_product_additional_info', $product_additional_info);
    foreach (array('_bamero_ral_code', '_bamero_resin_base', '_bamero_drying_time', '_bamero_coverage', '_bamero_voc', '_bamero_density', '_bamero_surface') as $field_id) {
        update_post_meta($post_id, $field_id, isset($_POST[$field_id]) ? sanitize_text_field(wp_unslash($_POST[$field_id])) : '');
    }
    update_post_meta($post_id, '_bamero_msds_url', isset($_POST['_bamero_msds_url']) ? esc_url_raw(wp_unslash($_POST['_bamero_msds_url'])) : '');
}
add_action('woocommerce_process_product_meta', 'bamero_save_custom_product_fields');

// Display custom product fields
function bamero_display_custom_product_fields() {
    global $product;

    $color_code = get_post_meta($product->get_id(), '_product_color_code', true);
    $brand = get_post_meta($product->get_id(), '_product_brand', true);
    $additional_info = get_post_meta($product->get_id(), '_product_additional_info', true);

    if (!empty($color_code)) {
        echo '<div class="product-color-code"><strong>کد رنگ:</strong> <span style="background-color: ' . esc_attr($color_code) . '; color: ' . bamero_get_contrast_color($color_code) . '; padding: 0.25rem 0.5rem; border-radius: 3px;">' . esc_html($color_code) . '</span></div>';
    }

    if (!empty($brand)) {
        echo '<div class="product-brand"><strong>برند:</strong> <span>' . esc_html($brand) . '</span></div>';
    }

    if (!empty($additional_info)) {
        echo '<div class="product-additional-info"><strong>اطلاعات اضافی:</strong> <p>' . wp_kses_post(nl2br($additional_info)) . '</p></div>';
    }
}
add_action('woocommerce_product_meta_end', 'bamero_display_custom_product_fields', 10);

// Get contrast color for text
function bamero_get_contrast_color($hex_color) {
    if (!is_string($hex_color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $hex_color)) {
        return '#000000';
    }
    $r = hexdec(substr($hex_color, 1, 2));
    $g = hexdec(substr($hex_color, 3, 2));
    $b = hexdec(substr($hex_color, 5, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($brightness > 128) ? '#000000' : '#FFFFFF';
}

// Custom product tabs
function bamero_custom_product_tabs($tabs) {
    global $product;
    if (!$product instanceof WC_Product) {
        return $tabs;
    }

    $additional_info = get_post_meta($product->get_id(), '_product_additional_info', true);
    if (!empty($additional_info)) {
        $tabs['additional_info'] = array(
            'title' => 'اطلاعات بیشتر',
            'priority' => 50,
            'callback' => 'bamero_additional_info_tab_content',
        );
    }

    $tabs['bamero_specs'] = array('title' => 'مشخصات فنی', 'priority' => 30, 'callback' => 'bamero_product_specs_tab_content');
    $tabs['bamero_application'] = array('title' => 'راهنمای اجرا', 'priority' => 35, 'callback' => 'bamero_product_application_tab_content');
    $tabs['bamero_safety'] = array('title' => 'ایمنی و نگهداری', 'priority' => 40, 'callback' => 'bamero_product_safety_tab_content');
    return $tabs;
}
add_filter('woocommerce_product_tabs', 'bamero_custom_product_tabs');

function bamero_product_meta_value($key, $default = 'ثبت نشده') {
    global $product;
    if (!$product instanceof WC_Product) return $default;
    $value = get_post_meta($product->get_id(), $key, true);
    return $value !== '' ? $value : $default;
}

function bamero_product_specs_tab_content() {
    echo '<div class="bamero-technical-specs"><table><caption class="screen-reader-text">مشخصات فنی محصول</caption><tbody>';
    foreach (array('پایه رزین' => '_bamero_resin_base', 'زمان خشک شدن' => '_bamero_drying_time', 'پوشش‌دهی m²/L' => '_bamero_coverage', 'VOC' => '_bamero_voc', 'دانسیته' => '_bamero_density', 'سطح مناسب' => '_bamero_surface', 'کد RAL/NCS' => '_bamero_ral_code') as $label => $key) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td class="technical-value">' . esc_html(bamero_product_meta_value($key)) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function bamero_product_application_tab_content() {
    echo '<div class="bamero-application-guide"><p><strong>آماده‌سازی:</strong> سطح باید تمیز، خشک و عاری از چربی و گردوغبار باشد.</p><p><strong>ابزار:</strong> قلم‌مو، غلتک یا پیستوله متناسب با نوع محصول.</p><p><strong>شرایط اجرا:</strong> دمای محیط و سطح مطابق دیتاشیت رسمی محصول باشد.</p></div>';
}

function bamero_product_safety_tab_content() {
    $url = bamero_product_meta_value('_bamero_msds_url', '');
    echo '<div class="bamero-safety-guide"><p>پیش از مصرف، برچسب محصول و دستورالعمل ایمنی را مطالعه کنید. دور از دسترس کودک و دور از حرارت نگهداری شود.</p>';
    if ($url !== '') echo '<p><a class="button" href="' . esc_url($url) . '" target="_blank" rel="noopener">دریافت برگه MSDS</a></p>';
    echo '</div>';
}

function bamero_coverage_calculator() {
    if (!is_product()) return;
    $coverage = (float) bamero_product_meta_value('_bamero_coverage', '0');
    echo '<section class="bamero-coverage-calculator" aria-labelledby="bamero-coverage-title"><h3 id="bamero-coverage-title">ماشین‌حساب مقدار مورد نیاز</h3><p>مساحت سطح را وارد کنید؛ ضریب اطمینان ۱۰٪ در محاسبه لحاظ می‌شود.</p><label for="bamero-area">مساحت (مترمربع)</label><input id="bamero-area" type="number" min="0" step="0.1" inputmode="decimal"><output id="bamero-coverage-result" data-coverage="' . esc_attr((string) $coverage) . '" aria-live="polite">پوشش‌دهی محصول ثبت نشده است.</output></section>';
}
add_action('woocommerce_after_add_to_cart_form', 'bamero_coverage_calculator', 20);

// Additional info tab content
function bamero_additional_info_tab_content() {
    global $product;
    $additional_info = get_post_meta($product->get_id(), '_product_additional_info', true);
    echo '<div class="additional-info-content">' . wp_kses_post(nl2br($additional_info)) . '</div>';
}

// ===== CUSTOM SHORTCODES =====

// Color consultation shortcode.
function bamero_color_consultation_shortcode() {
    $status = isset($_GET['consultation_status']) ? sanitize_key(wp_unslash($_GET['consultation_status'])) : '';
    $messages = array(
        'success' => array('class' => 'woocommerce-message', 'text' => 'درخواست مشاوره شما با موفقیت ارسال شد.'),
        'error'   => array('class' => 'woocommerce-error', 'text' => 'ارسال درخواست ممکن نشد. لطفاً دوباره تلاش کنید.'),
        'invalid' => array('class' => 'woocommerce-error', 'text' => 'لطفاً همه فیلدها را به‌درستی تکمیل کنید.'),
    );

    ob_start();
    ?>
    <div class="color-consultation-form">
        <h2>مشاوره رنگ</h2>
        <p>برای دریافت مشاوره رایگان در مورد انتخاب رنگ، فرم زیر را تکمیل کنید.</p>
        <?php if (isset($messages[$status])) : ?>
            <div class="<?php echo esc_attr($messages[$status]['class']); ?>" role="status">
                <?php echo esc_html($messages[$status]['text']); ?>
            </div>
        <?php endif; ?>
        <form id="color-consultation-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('bamero_color_consultation', 'bamero_color_consultation_nonce'); ?>
            <input type="hidden" name="action" value="bamero_color_consultation">
            <div class="form-group">
                <label for="consultation-name">نام و نام خانوادگی:</label>
                <input type="text" id="consultation-name" name="name" required aria-required="true" autocomplete="name">
            </div>
            <div class="form-group">
                <label for="consultation-phone">موبایل:</label>
                <input type="tel" id="consultation-phone" name="phone" required aria-required="true" autocomplete="tel" inputmode="numeric" placeholder="09123456789">
            </div>
            <div class="form-group">
                <label for="consultation-message">پیام:</label>
                <textarea id="consultation-message" name="message" required aria-required="true"></textarea>
            </div>
            <button type="submit" class="button">ارسال درخواست</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bamero_color_consultation', 'bamero_color_consultation_shortcode');

function bamero_process_color_consultation() {
    if (!isset($_POST['bamero_color_consultation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bamero_color_consultation_nonce'])), 'bamero_color_consultation')) {
        wp_die(esc_html__('درخواست نامعتبر است. لطفاً دوباره تلاش کنید.', 'bamero'), '', array('response' => 403));
    }

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $phone = function_exists('bamero_normalize_ir_mobile') ? bamero_normalize_ir_mobile($phone) : preg_replace('/\D+/', '', $phone);
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('/');

    if (!$name || !$phone || !$message) {
        wp_safe_redirect(add_query_arg('consultation_status', 'invalid', $redirect_url));
        exit;
    }

    $queued = function_exists('bamero_queue_notification')
        ? bamero_queue_notification(0, get_current_user_id(), 'color_consultation', array('mobile' => $phone, 'name' => $name, 'message' => $message))
        : new WP_Error('notification_unavailable');
    wp_safe_redirect(add_query_arg('consultation_status', is_wp_error($queued) ? 'error' : 'success', $redirect_url));
    exit;
}
add_action('admin_post_bamero_color_consultation', 'bamero_process_color_consultation');
add_action('admin_post_nopriv_bamero_color_consultation', 'bamero_process_color_consultation');

// ===== CUSTOM WOOCOMMERCE FUNCTIONS =====

// Remove WooCommerce default styles
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

// Custom WooCommerce breadcrumb
function bamero_woocommerce_breadcrumb() {
    if (function_exists('woocommerce_breadcrumb')) {
        woocommerce_breadcrumb(array(
            'delimiter' => ' <i class="fas fa-chevron-left"></i> ',
            'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="مسیر ناوبری">',
            'wrap_after' => '</nav>',
        ));
    }
}

// Custom cart fragment
function bamero_woocommerce_header_add_to_cart_fragment($fragments) {
    $count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
    // Selector must match header.php: span.cart-count (GATE-03)
    $fragments['span.cart-count'] = '<span class="cart-count">' . esc_html((string) $count) . '</span>';
    return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'bamero_woocommerce_header_add_to_cart_fragment');

// Custom product search form
function bamero_product_search_form($form) {
    $form = '<form role="search" method="get" class="woocommerce-product-search" action="' . esc_url(home_url('/')) . '">';
    $field_id = 'woocommerce-product-search-field-' . uniqid();
    $form .= '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">' . _x('Search for:', 'label', 'woocommerce') . '</label>';
    $form .= '<input type="search" id="' . esc_attr($field_id) . '" class="search-field" placeholder="' . esc_attr__('جستجوی محصولات...', 'bamero') . '" value="' . get_search_query() . '" name="s" />';
    $form .= '<button type="submit" value="' . esc_attr_x('Search', 'submit button', 'woocommerce') . '"><i class="fas fa-search"></i></button>';
    $form .= '<input type="hidden" name="post_type" value="product" />';
    $form .= '</form>';
    return $form;
}
add_filter('get_product_search_form', 'bamero_product_search_form');

// ===== ACCESSIBILITY IMPROVEMENTS =====

// Add skip to content link
function bamero_skip_to_content_link() {
    echo '<a href="#main-content" class="skip-to-content">برو به محتوا</a>';
}
add_action('wp_body_open', 'bamero_skip_to_content_link');

// Navigation accessibility is handled via aria-label on <nav> in header.php
// Do not mutate menu item titles (breaks screen readers and SEO).

// ===== SECURITY IMPROVEMENTS =====

// Remove WordPress version from RSS feeds
function bamero_remove_wp_version_rss() {
    return '';
}
add_filter('the_generator', 'bamero_remove_wp_version_rss');

// Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Remove REST API links from head
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');

// Disable emojis
function bamero_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
}
add_action('init', 'bamero_disable_emojis');

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

// Limit post revisions
function bamero_limit_post_revisions($num, $post_id) {
    return 5; // Limit to 5 revisions
}
add_filter('wp_revisions_to_keep', 'bamero_limit_post_revisions', 10, 2);

// ===== CUSTOM TEMPLATE FUNCTIONS =====

// ===== CONTACT FORM HANDLER (native fallback) =====
function bamero_process_contact_submit() {
    if (!isset($_POST['bamero_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bamero_contact_nonce'])), 'bamero_contact')) {
        wp_die(esc_html__('درخواست نامعتبر است.', 'bamero'), '', array('response' => 403));
    }

    $name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $phone   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $phone   = function_exists('bamero_normalize_ir_mobile') ? bamero_normalize_ir_mobile($phone) : preg_replace('/\D+/', '', $phone);
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $redirect = wp_get_referer() ? wp_get_referer() : home_url('/');

    if (!$name || !$phone || !$message) {
        wp_safe_redirect(add_query_arg('contact_status', 'invalid', $redirect));
        exit;
    }

    $queued = function_exists('bamero_queue_notification')
        ? bamero_queue_notification(0, get_current_user_id(), 'contact_request', array('mobile' => $phone, 'name' => $name, 'message' => $message))
        : new WP_Error('notification_unavailable');
    wp_safe_redirect(add_query_arg('contact_status', is_wp_error($queued) ? 'error' : 'success', $redirect));
    exit;
}
add_action('admin_post_bamero_contact_submit', 'bamero_process_contact_submit');
add_action('admin_post_nopriv_bamero_contact_submit', 'bamero_process_contact_submit');

// =============================================================================
// SEO + GEO (2026 production) – entity, local, AI-overview friendly JSON-LD
// =============================================================================

/**
 * Organization + LocalBusiness + WebSite entity graph (single @graph).
 * Optimized for Google AI Overviews / entity resolution (H2 2025–2026).
 */
function bamero_output_entity_graph() {
    if (is_admin()) {
        return;
    }
    // SEO-03: Rank Math owns structured data when active
    if (defined('RANK_MATH_VERSION')) {
        return;
    }

    $site_name = get_bloginfo('name') ?: 'بامرو';
    $site_url  = home_url('/');
    $logo      = BAMERO_THEME_DIR . '/images/logo.png';
    $phone     = bamero_phone_e164();
    $address   = bamero_address_display();

    $graph = array(
        '@context' => 'https://schema.org',
        '@graph'   => array(
            array(
                '@type' => 'Organization',
                '@id'   => $site_url . '#organization',
                'name'  => $site_name,
                'url'   => $site_url,
                'logo'  => array(
                    '@type'  => 'ImageObject',
                    'url'    => $logo,
                    'width'  => 1264,
                    'height' => 1244,
                ),
                'sameAs' => array_filter(array(
                    get_theme_mod('bamero_instagram_url', ''),
                    get_theme_mod('bamero_telegram_url', ''),
                )),
                'contactPoint' => array(
                    '@type'       => 'ContactPoint',
                    'telephone'   => $phone,
                    'contactType' => 'customer service',
                    'areaServed'  => 'IR',
                    'availableLanguage' => array('fa', 'Persian'),
                ),
            ),
            array(
                '@type' => 'LocalBusiness',
                '@id'   => $site_url . '#localbusiness',
                'name'  => $site_name,
                'image' => $logo,
                'url'   => $site_url,
                'telephone' => $phone,
                'priceRange' => '$$',
                'address' => array(
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $address,
                    'addressLocality' => 'اصفهان',
                    'addressRegion'   => 'اصفهان',
                    'addressCountry'  => 'IR',
                ),
                // GEO: approximate central Isfahan — replace with exact coordinates for the store.
                'geo' => array(
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => 32.666756,
                    'longitude' => 51.644733,
                ),
                'areaServed' => array(
                    array('@type' => 'City', 'name' => 'اصفهان'),
                    array('@type' => 'Country', 'name' => 'ایران'),
                ),
                'openingHoursSpecification' => array(
                    array(
                        '@type'     => 'OpeningHoursSpecification',
                        'dayOfWeek' => array('Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'),
                        'opens'     => '08:00',
                        'closes'    => '17:00',
                    ),
                    array(
                        '@type'     => 'OpeningHoursSpecification',
                        'dayOfWeek' => 'Thursday',
                        'opens'     => '08:00',
                        'closes'    => '14:00',
                    ),
                ),
                'parentOrganization' => array('@id' => $site_url . '#organization'),
            ),
            array(
                '@type' => 'WebSite',
                '@id'   => $site_url . '#website',
                'url'   => $site_url,
                'name'  => $site_name,
                'inLanguage' => 'fa-IR',
                'publisher'  => array('@id' => $site_url . '#organization'),
                'potentialAction' => array(
                    '@type'       => 'SearchAction',
                    'target'      => array(
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $site_url . '?s={search_term_string}&post_type=product',
                    ),
                    'query-input' => 'required name=search_term_string',
                ),
            ),
        ),
    );

    // Product entity on single product (E-E-A-T + rich results)
    if (function_exists('is_product') && is_product()) {
        global $product;
        if ($product instanceof WC_Product) {
            $graph['@graph'][] = array(
                '@type' => 'Product',
                '@id'   => get_permalink() . '#product',
                'name'  => $product->get_name(),
                'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
                'sku'   => $product->get_sku(),
                'brand' => array(
                    '@type' => 'Brand',
                    'name'  => 'بامرو',
                ),
                'offers' => array(
                    '@type'         => 'Offer',
                    'url'           => get_permalink(),
                    'priceCurrency' => get_woocommerce_currency(),
                    'price'         => $product->get_price(),
                    'availability'  => $product->is_in_stock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'seller'        => array('@id' => $site_url . '#organization'),
                ),
            );
        }
    }

    // BreadcrumbList (helps AI Overviews + sitelinks)
    if (!is_front_page()) {
        $crumbs = array(
            array(
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'خانه',
                'item'     => $site_url,
            ),
        );
        $pos = 2;
        if (function_exists('is_shop') && is_shop()) {
            $crumbs[] = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'فروشگاه',
                'item'     => get_permalink(wc_get_page_id('shop')),
            );
        } elseif (is_singular()) {
            $crumbs[] = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => get_the_title(),
                'item'     => get_permalink(),
            );
        }
        $graph['@graph'][] = array(
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        );
    }

    echo '<script type="application/ld+json" nonce="' . esc_attr( function_exists( 'bamero_csp_nonce' ) ? bamero_csp_nonce() : '' ) . '">' . wp_json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'bamero_output_entity_graph', 5);

/** Meta robots + canonical helpers for thin/archive pages */
function bamero_meta_robots() {
    if (is_search() || is_404()) {
        echo '<meta name="robots" content="noindex, follow" />' . "\n";
    }
}
add_action('wp_head', 'bamero_meta_robots', 1);

/** hreflang for fa-IR (GEO + language) */
function bamero_hreflang() {
    if (is_singular()) {
        $url = get_permalink();
    } elseif (function_exists('is_shop') && is_shop()) {
        $url = get_permalink(wc_get_page_id('shop'));
    } else {
        $url = home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''));
    }
    if (!$url) {
        $url = home_url('/');
    }
    echo '<link rel="alternate" hreflang="fa-IR" href="' . esc_url($url) . '" />' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($url) . '" />' . "\n";
}
add_action('wp_head', 'bamero_hreflang', 2);

// =============================================================================
// PERFORMANCE (Core Web Vitals 2026: LCP, INP, CLS)
// =============================================================================

/** Remove emoji, embed, and other bloat */
function bamero_disable_bloat() {
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rest_output_link_wp_head');
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

// =============================================================================
// DATABASE / QUERY hygiene
// =============================================================================

/** Limit heartbeats already present; limit post revisions already present */

/** Disable unnecessary revisions for products (Woo meta-heavy) */
add_filter('wp_revisions_to_keep', function ($num, $post) {
    if (isset($post->post_type) && $post->post_type === 'product') {
        return 3;
    }
    return is_numeric($num) ? min(5, (int) $num) : 5;
}, 10, 2);

/** Prevent excessive autosave interval load */
if (!defined('AUTOSAVE_INTERVAL')) {
    define('AUTOSAVE_INTERVAL', 120);
}

// =============================================================================
// CONTACT DETAILS + CUSTOMIZER (owner-configurable; no hard-coded brand values)
// =============================================================================

/** Convert Persian/Arabic-Indic digits to ASCII. */
function bamero_ascii_digits($value) {
    $map = array(
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    );
    return strtr((string) $value, $map);
}

/** Display phone number exactly as the owner entered it. */
function bamero_phone_display() {
    return (string) get_theme_mod('bamero_phone_number', '۰۹۱۳۴۲۹۲۳۲۹');
}

/** Phone number in E.164 form (for tel: links), derived from the display value. */
function bamero_phone_e164() {
    $digits = preg_replace('/\D+/', '', bamero_ascii_digits(bamero_phone_display()));
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '98') === 0) {
        return '+' . $digits;
    }
    if ($digits[0] === '0') {
        return '+98' . substr($digits, 1);
    }
    return '+98' . $digits;
}

/** Store address as configured by the owner. */
function bamero_address_display() {
    return (string) get_theme_mod('bamero_address', 'اصفهان، خیابان خرم، نرسیده به خیابان صارمیه');
}

/** Default WhatsApp deep link derived from the configured phone number. */
function bamero_whatsapp_default() {
    $digits = preg_replace('/\D+/', '', bamero_ascii_digits(bamero_phone_display()));
    return $digits === '' ? '' : 'https://wa.me/' . $digits;
}

/** Register owner-configurable storefront settings (Appearance > Customize). */
function bamero_customize_register($wp_customize) {
    $wp_customize->add_section('bamero_contact', array(
        'title'       => __('اطلاعات تماس بامرو', 'bamero'),
        'description' => __('این مقادیر در سربرگ، پاورقی و داده‌های ساختاریافته (Schema) استفاده می‌شوند.', 'bamero'),
        'priority'    => 30,
    ));

    $fields = array(
        'bamero_phone_number'  => array('label' => __('شماره تماس', 'bamero'), 'type' => 'text', 'default' => '۰۹۱۳۴۲۹۲۳۲۹'),
        'bamero_address'       => array('label' => __('نشانی', 'bamero'), 'type' => 'textarea', 'default' => 'اصفهان، خیابان خرم، نرسیده به خیابان صارمیه'),
        'bamero_instagram_url' => array('label' => __('آدرس اینستاگرام', 'bamero'), 'type' => 'url', 'default' => ''),
        'bamero_telegram_url'  => array('label' => __('آدرس تلگرام', 'bamero'), 'type' => 'url', 'default' => ''),
        'bamero_whatsapp_url'  => array('label' => __('آدرس واتساپ', 'bamero'), 'type' => 'url', 'default' => ''),
    );

    foreach ($fields as $id => $field) {
        if ($field['type'] === 'url') {
            $sanitize = 'esc_url_raw';
        } elseif ($field['type'] === 'textarea') {
            $sanitize = 'sanitize_textarea_field';
        } else {
            $sanitize = 'sanitize_text_field';
        }
        $wp_customize->add_setting($id, array(
            'default'           => $field['default'],
            'sanitize_callback' => $sanitize,
            'transport'         => 'refresh',
        ));
        $wp_customize->add_control($id, array(
            'label'   => $field['label'],
            'section' => 'bamero_contact',
            'type'    => $field['type'],
        ));
    }
}
add_action('customize_register', 'bamero_customize_register');

// =============================================================================
// DYNAMIC robots.txt (domain-agnostic; no hard-coded production host)
// =============================================================================

/**
 * Serve robots.txt dynamically so the Sitemap URL always matches the real
 * domain the owner configures under Settings > General. A static file would
 * hard-code a host that does not exist yet.
 */
function bamero_robots_txt($output, $public) {
    if (!$public) {
        return $output;
    }
    if (stripos($output, 'Sitemap:') === false) {
        $output = rtrim($output) . "\n\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
    }
    return $output;
}
add_filter('robots_txt', 'bamero_robots_txt', 99, 2);