<?php
/**
 * Bamero Theme — WooCommerce integration
 *
 * Persian text mapping, admin product fields, front-end product data,
 * custom tabs, coverage calculator, cart fragment (GATE-03) and the
 * product search form.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

// ===== WOOCOMMERCE CUSTOMIZATIONS =====

// Change WooCommerce texts to Persian
function bamero_woocommerce_persian_text($translated_text, $text, $domain) {
    if ($domain === 'woocommerce') {
        // Static cache: build the map once per request instead of on every gettext call.
        static $translations = null;
        if ($translations === null) {
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
            'Sort by newness' => 'مرتب‌سازی بر اساس جدیدترین محصولات',
            'Sort by price: low to high' => 'مرتب‌سازی بر اساس قیمت: ارزان به گران',
            'Sort by price: high to low' => 'مرتب‌سازی بر اساس قیمت: گران به ارزان',
            'In stock' => 'موجود',
            'Out of stock' => 'ناموجود',
            'Add to wishlist' => 'افزودن به لیست علاقه‌مندی',
            'Remove from wishlist' => 'حذف از لیست علاقه‌مندی',
            'Your wishlist' => 'لیست علاقه‌مندی شما',
            'No products in the wishlist' => 'هیچ محصولی در لیست علاقه‌مندی وجود ندارد',
            'Proceed to checkout' => 'ادامه جهت پرداخت',
            'Update cart' => 'به‌روزرسانی سبد خرید',
            'Cart totals' => 'جمع سبد خرید',
            'Subtotal' => 'جمع جزء',
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
            'Apply coupon' => 'اعمال کد تخفیف',
            'Coupon code' => 'کد تخفیف',
            'Enter your coupon code if you have one' => 'اگر کد تخفیف دارید، وارد کنید',
            'Coupon has been applied successfully' => 'کد تخفیف با موفقیت اعمال شد',
            'Sorry, this coupon does not exist' => 'متأسفانه این کد تخفیف معتبر نیست',
            'Please enter a coupon code' => 'لطفاً کد تخفیف را وارد کنید',
            );
        }

        if (isset($translations[$text])) {
            return $translations[$text];
        }
    }
    return $translated_text;
}
add_filter('gettext', 'bamero_woocommerce_persian_text', 20, 3);

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

// ===== CUSTOM WOOCOMMERCE FUNCTIONS =====

// Remove WooCommerce default styles
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

// WooCommerce breadcrumb args — canonical filter (the previous wrapper was never
// registered, so WooCommerce rendered the default English breadcrumb).
function bamero_woocommerce_breadcrumb_defaults($defaults) {
    $defaults['delimiter'] = ' <i class="fas fa-chevron-left" aria-hidden="true"></i> ';
    $defaults['wrap_before'] = '<nav class="woocommerce-breadcrumb" aria-label="' . esc_attr__('مسیر ناوبری', 'bamero') . '">';
    $defaults['wrap_after'] = '</nav>';
    $defaults['home'] = __('خانه', 'bamero');
    return $defaults;
}
add_filter('woocommerce_breadcrumb_defaults', 'bamero_woocommerce_breadcrumb_defaults');

// Custom cart fragment
function bamero_woocommerce_header_add_to_cart_fragment($fragments) {
    $count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
    // Selector must match header.php: span.cart-count (GATE-03)
    $fragments['span.cart-count'] = '<span class="cart-count">' . esc_html(bamero_persian_digits((string) $count)) . '</span>';
    return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'bamero_woocommerce_header_add_to_cart_fragment');

// Custom product search form
function bamero_product_search_form($form) {
    $form = '<form role="search" method="get" class="woocommerce-product-search" action="' . esc_url(home_url('/')) . '">';
    $field_id = 'woocommerce-product-search-field-' . uniqid();
    $form .= '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">' . _x('Search for:', 'label', 'woocommerce') . '</label>';
    $form .= '<input type="search" id="' . esc_attr($field_id) . '" class="search-field" placeholder="' . esc_attr__('جستجوی محصولات...', 'bamero') . '" value="' . esc_attr(get_search_query()) . '" name="s" />';
    $form .= '<button type="submit" value="' . esc_attr_x('Search', 'submit button', 'woocommerce') . '"><i class="fas fa-search"></i></button>';
    $form .= '<input type="hidden" name="post_type" value="product" />';
    $form .= '</form>';
    return $form;
}
add_filter('get_product_search_form', 'bamero_product_search_form');

