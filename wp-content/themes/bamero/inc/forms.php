<?php
/**
 * Bamero Theme — Forms and Customizer
 *
 * Color-consultation and contact form handlers (admin-post,
 * nonce-verified) and the owner-configurable storefront settings under
 * Appearance > Customize.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

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

