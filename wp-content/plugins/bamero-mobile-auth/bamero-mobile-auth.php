<?php
/**
 * Plugin Name: Bamero Mobile Auth
 * Description: Customer identity by verified mobile + SMS OTP. No customer password. No customer email authentication.
 * Version: 1.0.0
 * Author: Bamero
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bamero-mobile-auth
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('BAMERO_MOBILE_AUTH_VERSION', '1.0.0');
define('BAMERO_MOBILE_AUTH_OTP_TTL', 120);      // OTP validity window: 2 minutes
define('BAMERO_MOBILE_AUTH_OTP_LENGTH', 6);
define('BAMERO_MOBILE_AUTH_RATE_LIMIT', 5);     // max OTP requests per phone per hour
define('BAMERO_MOBILE_AUTH_MAX_ATTEMPTS', 3);   // max verification attempts per OTP
define('BAMERO_MOBILE_AUTH_LOCKOUT', 900);      // 15-minute lockout after repeated failed verifications
define('BAMERO_MOBILE_AUTH_META_PHONE', 'bamero_verified_mobile');

/**
 * Normalize Iranian mobile to 989xxxxxxxxx (digits only, country code 98).
 *
 * @param string $raw Raw input.
 * @return string|false Normalized phone or false.
 */
function bamero_normalize_ir_mobile($raw) {
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') {
        return false;
    }
    // 09xxxxxxxxx → 989xxxxxxxxx
    if (preg_match('/^09\d{9}$/', $digits)) {
        return '98' . substr($digits, 1);
    }
    // 9xxxxxxxxx → 989xxxxxxxxx
    if (preg_match('/^9\d{9}$/', $digits)) {
        return '98' . $digits;
    }
    // 989xxxxxxxxx
    if (preg_match('/^989\d{9}$/', $digits)) {
        return $digits;
    }
    // +98 already stripped by \D
    return false;
}

function bamero_auth_schema_validate($phone_raw, $otp_raw = null, $context = 'login') {
    $phone = bamero_normalize_ir_mobile($phone_raw);
    if (!$phone || !in_array($context, array('login', 'register'), true)) {
        return new WP_Error('auth_schema_invalid', __('ساختار درخواست احراز هویت نامعتبر است.', 'bamero-mobile-auth'));
    }
    if (null !== $otp_raw) {
        $otp = preg_replace('/\D+/', '', (string) $otp_raw);
        if (strlen($otp) !== BAMERO_MOBILE_AUTH_OTP_LENGTH) {
            return new WP_Error('auth_schema_invalid', __('ساختار کد تأیید نامعتبر است.', 'bamero-mobile-auth'));
        }
    }
    return array('phone' => $phone, 'context' => $context);
}

/**
 * Format for display: 09xx xxx xxxx
 */
function bamero_format_mobile_display($normalized) {
    if (!$normalized || strlen($normalized) < 12) {
        return $normalized;
    }
    $local = '0' . substr($normalized, 2);
    return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7);
}

/**
 * Generate cryptographically strong OTP.
 */
function bamero_generate_otp() {
    $max = (10 ** BAMERO_MOBILE_AUTH_OTP_LENGTH) - 1;
    $num = random_int(0, $max);
    return str_pad((string) $num, BAMERO_MOBILE_AUTH_OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Transient key for OTP challenge.
 */
function bamero_otp_transient_key($phone) {
    return 'bamero_otp_' . md5($phone);
}

/**
 * Request OTP for registration or login.
 */
function bamero_request_otp($phone_raw, $context = 'login') {
    $schema = bamero_auth_schema_validate($phone_raw, null, $context);
    if (is_wp_error($schema)) return $schema;
    $phone = $schema['phone'];

    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    $phone_key  = 'bamero_otp_rate_p_' . md5($phone);
    $ip_key     = 'bamero_otp_rate_ip_' . md5($ip);
    $global_key = 'bamero_otp_rate_global';

    // H2: the check-then-increment below is a read-modify-write cycle over
    // transients. Under concurrent requests (double-click, retry storms) the
    // non-atomic version lets far more than the configured number of SMS
    // messages through. Serialize the whole section per phone with a MySQL
    // named lock so the counters are enforced exactly.
    global $wpdb;
    $lock_name = 'bamero_otp_rl_' . md5($phone);
    $got_lock  = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));
    if (1 !== $got_lock) {
        return new WP_Error('rate_limited', __('تعداد درخواست‌ها بیش از حد مجاز است. بعداً تلاش کنید.', 'bamero-mobile-auth'));
    }

    try {
        foreach (array($phone_key => BAMERO_MOBILE_AUTH_RATE_LIMIT, $ip_key => BAMERO_MOBILE_AUTH_RATE_LIMIT * 3, $global_key => 1000) as $rk => $limit) {
            $count = (int) get_transient($rk);
            if ($count >= $limit) {
                return new WP_Error('rate_limited', __('تعداد درخواست‌ها بیش از حد مجاز است. بعداً تلاش کنید.', 'bamero-mobile-auth'));
            }
        }
        foreach (array($phone_key, $ip_key, $global_key) as $rk) {
            set_transient($rk, (int) get_transient($rk) + 1, HOUR_IN_SECONDS);
        }
    } finally {
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }

    $lock_key = 'bamero_otp_lock_' . md5($phone);
    if (get_transient($lock_key)) {
        return new WP_Error('locked', __('به دلیل تلاش‌های ناموفق موقتاً قفل شده است.', 'bamero-mobile-auth'));
    }

    $otp = bamero_generate_otp();
    $hash = wp_hash_password($otp);

    set_transient(
        bamero_otp_transient_key($phone),
        array(
            'hash'     => $hash,
            'context'  => sanitize_key($context),
            'created'  => time(),
            'attempts' => 0,
        ),
        BAMERO_MOBILE_AUTH_OTP_TTL
    );

    $payload = array('mobile' => $phone, 'otp' => $otp, 'context' => sanitize_key($context));
    if (function_exists('bamero_queue_notification')) {
        $queued = bamero_queue_notification(0, 0, 'login_otp', $payload);
        if (is_wp_error($queued)) {
            delete_transient(bamero_otp_transient_key($phone));
            return $queued;
        }
    } else {
        delete_transient(bamero_otp_transient_key($phone));
        return new WP_Error('sms_queue_unavailable', __('سامانهٔ پیامک موقتاً در دسترس نیست.', 'bamero-mobile-auth'));
    }

    return array(
        'success' => true,
        'phone'   => bamero_format_mobile_display($phone),
        'ttl'     => BAMERO_MOBILE_AUTH_OTP_TTL,
    );
}

/**
 * Verify OTP and return normalized phone on success.
 */
function bamero_verify_otp($phone_raw, $otp_raw) {
    $schema = bamero_auth_schema_validate($phone_raw, $otp_raw, 'login');
    if (is_wp_error($schema)) return $schema;
    $phone = $schema['phone'];
    $otp = preg_replace('/\D+/', '', (string) $otp_raw);

    $data = get_transient(bamero_otp_transient_key($phone));
    if (!$data || empty($data['hash'])) {
        return new WP_Error('otp_expired', __('کد منقضی شده است. دوباره درخواست دهید.', 'bamero-mobile-auth'));
    }

    $attempts = isset($data['attempts']) ? (int) $data['attempts'] : 0;
    if ($attempts >= BAMERO_MOBILE_AUTH_MAX_ATTEMPTS) {
        set_transient('bamero_otp_lock_' . md5($phone), 1, BAMERO_MOBILE_AUTH_LOCKOUT);
        delete_transient(bamero_otp_transient_key($phone));
        return new WP_Error('locked', __('تعداد تلاش بیش از حد. ۱۵ دقیقه قفل شدید.', 'bamero-mobile-auth'));
    }

    if (!wp_check_password($otp, $data['hash'])) {
        $data['attempts'] = $attempts + 1;
        set_transient(bamero_otp_transient_key($phone), $data, BAMERO_MOBILE_AUTH_OTP_TTL);
        return new WP_Error('otp_mismatch', __('کد تأیید اشتباه است.', 'bamero-mobile-auth'));
    }

    delete_transient(bamero_otp_transient_key($phone));
    return array('phone' => $phone, 'context' => isset($data['context']) ? $data['context'] : 'login');
}

/**
 * Find WP user by verified mobile meta or login = phone.
 */
function bamero_get_user_by_mobile($normalized_phone) {
    $users = get_users(array(
        'meta_key'   => BAMERO_MOBILE_AUTH_META_PHONE,
        'meta_value' => $normalized_phone,
        'number'     => 1,
        'fields'     => 'all',
    ));
    if (!empty($users)) {
        return $users[0];
    }
    // Fallback: user_login stored as phone
    $by_login = get_user_by('login', $normalized_phone);
    return $by_login ?: null;
}

/**
 * Register customer: mobile (verified) + first name + last name. No password, no email.
 * WordPress requires user_email technically — use phone-based synthetic internal address
 * that is never used for auth or customer communication (platform constraint).
 */
function bamero_register_customer($normalized_phone, $first_name, $last_name) {
    if (!preg_match('/^989\d{9}$/', (string) $normalized_phone)) {
        return new WP_Error('schema_invalid', __('شماره موبایل ثبت‌نام معتبر نیست.', 'bamero-mobile-auth'));
    }
    $first_name = sanitize_text_field($first_name);
    $last_name  = sanitize_text_field($last_name);
    if ($first_name === '' || $last_name === '') {
        return new WP_Error('name_required', __('نام و نام خانوادگی الزامی است.', 'bamero-mobile-auth'));
    }

    if (bamero_get_user_by_mobile($normalized_phone)) {
        return new WP_Error('exists', __('این شماره قبلاً ثبت شده است. وارد شوید.', 'bamero-mobile-auth'));
    }

    // Internal-only mailbox placeholder — not for customer messaging or auth recovery.
    // Required by WP user schema; never used as identity.
    $salt = (string) getenv('BAMERO_INTERNAL_ID_SALT');
    if ($salt === '') { $salt = wp_salt('auth'); }
    $internal_email = hash('sha256', $normalized_phone . $salt) . '@bamero.internal';

    $user_id = wp_insert_user(array(
        'user_login'   => $normalized_phone,
        'user_pass'    => wp_generate_password(32, true, true), // random; never shown; login is OTP-only
        'user_email'   => $internal_email,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name),
        'role'         => 'customer',
    ));

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    update_user_meta($user_id, BAMERO_MOBILE_AUTH_META_PHONE, $normalized_phone);
    update_user_meta($user_id, 'billing_phone', bamero_format_mobile_display($normalized_phone));
    update_user_meta($user_id, 'billing_first_name', $first_name);
    update_user_meta($user_id, 'billing_last_name', $last_name);
    // Explicitly no billing_email for customer flows
    update_user_meta($user_id, 'billing_email', '');

    return $user_id;
}

/**
 * Log user in via WordPress auth cookies (existing platform session architecture).
 */
function bamero_login_user($user_id) {
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
}

// ----- REST-ish admin-post endpoints (form-friendly, nonce protected) -----

function bamero_handle_request_otp() {
    if (!isset($_POST['bamero_otp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bamero_otp_nonce'])), 'bamero_request_otp')) {
        wp_die(esc_html__('درخواست نامعتبر.', 'bamero-mobile-auth'), '', array('response' => 403));
    }
    $phone   = isset($_POST['mobile']) ? wp_unslash($_POST['mobile']) : '';
    $context = isset($_POST['context']) ? sanitize_key(wp_unslash($_POST['context'])) : 'login';
    $result  = bamero_request_otp($phone, $context);
    $redirect = wp_get_referer() ? wp_get_referer() : home_url('/');
    if (is_wp_error($result)) {
        if (function_exists('bamero_log_event')) {
            bamero_log_event('otp_request_failed', array('context' => $context, 'reason' => $result->get_error_code()));
        }
        wp_safe_redirect(add_query_arg('bamero_auth', $result->get_error_code(), $redirect));
        exit;
    }
    if (function_exists('bamero_log_event')) {
        bamero_log_event('otp_requested', array('context' => $context));
    }
    wp_safe_redirect(add_query_arg(array(
        'bamero_auth' => 'otp_sent',
        'mobile'      => $result['phone'],
    ), $redirect));
    exit;
}
add_action('admin_post_nopriv_bamero_request_otp', 'bamero_handle_request_otp');
add_action('admin_post_bamero_request_otp', 'bamero_handle_request_otp');

function bamero_handle_verify_otp() {
    if (!isset($_POST['bamero_otp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bamero_otp_nonce'])), 'bamero_verify_otp')) {
        wp_die(esc_html__('درخواست نامعتبر.', 'bamero-mobile-auth'), '', array('response' => 403));
    }
    $phone_raw = isset($_POST['mobile']) ? wp_unslash($_POST['mobile']) : '';
    $otp_raw   = isset($_POST['otp']) ? wp_unslash($_POST['otp']) : '';
    $context   = isset($_POST['context']) ? sanitize_key(wp_unslash($_POST['context'])) : 'login';
    $first     = isset($_POST['first_name']) ? wp_unslash($_POST['first_name']) : '';
    $last      = isset($_POST['last_name']) ? wp_unslash($_POST['last_name']) : '';

    $schema = bamero_auth_schema_validate($phone_raw, $otp_raw, $context);
    if (is_wp_error($schema)) {
        $redirect = wp_get_referer() ? wp_get_referer() : home_url('/');
        wp_safe_redirect(add_query_arg('bamero_auth', $schema->get_error_code(), $redirect));
        exit;
    }
    $verified = bamero_verify_otp($phone_raw, $otp_raw);
    $redirect = wp_get_referer() ? wp_get_referer() : home_url('/');

    if (is_wp_error($verified)) {
        if (function_exists('bamero_log_event')) {
            bamero_log_event('otp_verify_failed', array('context' => $context, 'reason' => $verified->get_error_code()));
        }
        // Throttle: emit HTTP 429 for lockout after too many failed attempts.
        if ('locked' === $verified->get_error_code()) {
            nocache_headers();
            wp_die(esc_html($verified->get_error_message()), '', array('response' => 429));
        }
        wp_safe_redirect(add_query_arg('bamero_auth', $verified->get_error_code(), $redirect));
        exit;
    }

    // AUTH-02: POST context must match transient context
    $tx_context = is_array($verified) ? $verified['context'] : 'login';
    $phone_norm = is_array($verified) ? $verified['phone'] : $verified;
    if ($context !== $tx_context) {
        wp_safe_redirect(add_query_arg('bamero_auth', 'context_mismatch', $redirect));
        exit;
    }

    $user = bamero_get_user_by_mobile($phone_norm);

    if ($context === 'register') {
        if ($user) {
            wp_safe_redirect(add_query_arg('bamero_auth', 'exists', $redirect));
            exit;
        }
        $user_id = bamero_register_customer($phone_norm, $first, $last);
        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('bamero_auth', $user_id->get_error_code(), $redirect));
            exit;
        }
        bamero_login_user($user_id);
        if (function_exists('bamero_log_event')) {
            bamero_log_event('customer_registered', array('user_id' => (int) $user_id));
        }
        wp_safe_redirect(add_query_arg('bamero_auth', 'registered', wc_get_page_permalink('myaccount') ?: home_url('/')));
        exit;
    }

    // login
    if (!$user) {
        wp_safe_redirect(add_query_arg('bamero_auth', 'not_registered', $redirect));
        exit;
    }
    bamero_login_user($user->ID);
    if (function_exists('bamero_log_event')) {
        bamero_log_event('customer_logged_in', array('user_id' => (int) $user->ID));
    }
    wp_safe_redirect(add_query_arg('bamero_auth', 'logged_in', wc_get_page_permalink('myaccount') ?: home_url('/')));
    exit;
}
add_action('admin_post_nopriv_bamero_verify_otp', 'bamero_handle_verify_otp');
add_action('admin_post_bamero_verify_otp', 'bamero_handle_verify_otp');

/**
 * WooCommerce: make email optional / not required for checkout account flows.
 */
function bamero_wc_checkout_fields($fields) {
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['label']    = __('ایمیل (اختیاری)', 'bamero-mobile-auth');
    }
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['required'] = true;
        $fields['billing']['billing_phone']['label']    = __('موبایل', 'bamero-mobile-auth');
        $fields['billing']['billing_phone']['priority'] = 25;
    }
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'bamero_wc_checkout_fields');

/** Disable WC customer account creation requiring email password on checkout */
add_filter('woocommerce_checkout_registration_enabled', '__return_false');
add_filter('woocommerce_enable_myaccount_registration', '__return_false');

/**
 * Shortcode: [bamero_mobile_auth] — login/register OTP UI
 */
function bamero_mobile_auth_shortcode() {
    if (is_user_logged_in()) {
        $u = wp_get_current_user();
        $phone = get_user_meta($u->ID, BAMERO_MOBILE_AUTH_META_PHONE, true);
        ob_start();
        echo '<div class="bamero-auth-status">';
        echo '<p>' . esc_html(sprintf(__('وارد شده‌اید: %s', 'bamero-mobile-auth'), $u->display_name)) . '</p>';
        if ($phone) {
            echo '<p>' . esc_html(bamero_format_mobile_display($phone)) . '</p>';
        }
        echo '<p><a class="button" href="' . esc_url(wp_logout_url(home_url('/'))) . '">' . esc_html__('خروج', 'bamero-mobile-auth') . '</a></p>';
        echo '</div>';
        return ob_get_clean();
    }

    $status = isset($_GET['bamero_auth']) ? sanitize_key(wp_unslash($_GET['bamero_auth'])) : '';
    $mobile_q = isset($_GET['mobile']) ? sanitize_text_field(wp_unslash($_GET['mobile'])) : '';
    $messages = array(
        'otp_sent'       => __('کد تأیید ارسال شد.', 'bamero-mobile-auth'),
        'otp_expired'    => __('کد منقضی شد.', 'bamero-mobile-auth'),
        'otp_mismatch'   => __('کد اشتباه است.', 'bamero-mobile-auth'),
        'invalid_mobile' => __('شماره موبایل نامعتبر است.', 'bamero-mobile-auth'),
        'rate_limited'   => __('محدودیت تعداد پیامک.', 'bamero-mobile-auth'),
        'not_registered' => __('این شماره ثبت نشده. ثبت‌نام کنید.', 'bamero-mobile-auth'),
        'exists'         => __('قبلاً ثبت‌نام کرده‌اید. وارد شوید.', 'bamero-mobile-auth'),
        'name_required'  => __('نام و نام خانوادگی الزامی است.', 'bamero-mobile-auth'),
        'bamero_sms_not_configured' => __('ارسال پیامک هنوز پیکربندی نشده (پیش‌نیاز خارجی).', 'bamero-mobile-auth'),
    );

    ob_start();
    ?>
    <div class="bamero-mobile-auth" id="bamero-mobile-auth">
        <?php if ($status && isset($messages[$status])) : ?>
            <div class="woocommerce-info" role="status"><?php echo esc_html($messages[$status]); ?></div>
        <?php endif; ?>

        <div class="bamero-auth-tabs">
            <button type="button" class="bamero-tab active" data-tab="login"><?php echo esc_html__('ورود', 'bamero-mobile-auth'); ?></button>
            <button type="button" class="bamero-tab" data-tab="register"><?php echo esc_html__('ثبت‌نام', 'bamero-mobile-auth'); ?></button>
        </div>

        <!-- LOGIN -->
        <div class="bamero-auth-panel" data-panel="login">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bamero-otp-request">
                <?php wp_nonce_field('bamero_request_otp', 'bamero_otp_nonce'); ?>
                <input type="hidden" name="action" value="bamero_request_otp">
                <input type="hidden" name="context" value="login">
                <p>
                    <label for="bamero-login-mobile"><?php echo esc_html__('شماره موبایل', 'bamero-mobile-auth'); ?></label>
                    <input type="tel" id="bamero-login-mobile" name="mobile" required
                           inputmode="numeric" autocomplete="tel"
                           placeholder="09123456789"
                           value="<?php echo esc_attr($mobile_q); ?>">
                </p>
                <button type="submit" class="button"><?php echo esc_html__('دریافت کد تأیید', 'bamero-mobile-auth'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bamero-otp-verify">
                <?php wp_nonce_field('bamero_verify_otp', 'bamero_otp_nonce'); ?>
                <input type="hidden" name="action" value="bamero_verify_otp">
                <input type="hidden" name="context" value="login">
                <p>
                    <label for="bamero-login-mobile2"><?php echo esc_html__('شماره موبایل', 'bamero-mobile-auth'); ?></label>
                    <input type="tel" id="bamero-login-mobile2" name="mobile" required value="<?php echo esc_attr($mobile_q); ?>">
                </p>
                <p>
                    <label for="bamero-login-otp"><?php echo esc_html__('کد تأیید پیامک', 'bamero-mobile-auth'); ?></label>
                    <input type="text" id="bamero-login-otp" name="otp" required inputmode="numeric"
                           pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
                </p>
                <button type="submit" class="button"><?php echo esc_html__('ورود', 'bamero-mobile-auth'); ?></button>
            </form>
        </div>

        <!-- REGISTER -->
        <div class="bamero-auth-panel" data-panel="register" hidden>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bamero-otp-request">
                <?php wp_nonce_field('bamero_request_otp', 'bamero_otp_nonce'); ?>
                <input type="hidden" name="action" value="bamero_request_otp">
                <input type="hidden" name="context" value="register">
                <p>
                    <label for="bamero-reg-mobile"><?php echo esc_html__('شماره موبایل', 'bamero-mobile-auth'); ?></label>
                    <input type="tel" id="bamero-reg-mobile" name="mobile" required inputmode="numeric" autocomplete="tel" placeholder="09123456789">
                </p>
                <button type="submit" class="button"><?php echo esc_html__('دریافت کد تأیید', 'bamero-mobile-auth'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bamero-otp-verify">
                <?php wp_nonce_field('bamero_verify_otp', 'bamero_otp_nonce'); ?>
                <input type="hidden" name="action" value="bamero_verify_otp">
                <input type="hidden" name="context" value="register">
                <p>
                    <label for="bamero-reg-mobile2"><?php echo esc_html__('شماره موبایل', 'bamero-mobile-auth'); ?></label>
                    <input type="tel" id="bamero-reg-mobile2" name="mobile" required>
                </p>
                <p>
                    <label for="bamero-reg-otp"><?php echo esc_html__('کد تأیید پیامک', 'bamero-mobile-auth'); ?></label>
                    <input type="text" id="bamero-reg-otp" name="otp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
                </p>
                <p>
                    <label for="bamero-first"><?php echo esc_html__('نام', 'bamero-mobile-auth'); ?></label>
                    <input type="text" id="bamero-first" name="first_name" required autocomplete="given-name">
                </p>
                <p>
                    <label for="bamero-last"><?php echo esc_html__('نام خانوادگی', 'bamero-mobile-auth'); ?></label>
                    <input type="text" id="bamero-last" name="last_name" required autocomplete="family-name">
                </p>
                <button type="submit" class="button"><?php echo esc_html__('ثبت‌نام و ورود', 'bamero-mobile-auth'); ?></button>
            </form>
        </div>
    </div>
    <script nonce="<?php echo esc_attr(function_exists('bamero_csp_nonce') ? bamero_csp_nonce() : ''); ?>">
    (function(){
        var root = document.getElementById('bamero-mobile-auth');
        if (!root) return;
        root.querySelectorAll('.bamero-tab').forEach(function(tab){
            tab.addEventListener('click', function(){
                root.querySelectorAll('.bamero-tab').forEach(function(t){ t.classList.remove('active'); });
                tab.classList.add('active');
                var name = tab.getAttribute('data-tab');
                root.querySelectorAll('.bamero-auth-panel').forEach(function(p){
                    p.hidden = p.getAttribute('data-panel') !== name;
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('bamero_mobile_auth', 'bamero_mobile_auth_shortcode');

/**
 * Create My Account reliance: if WC my-account page empty of form, owners can add shortcode.
 * On activation, ensure a page exists with the shortcode for login.
 */
function bamero_mobile_auth_activate() {
    $page = get_page_by_path('mobile-login');
    if (!$page) {
        wp_insert_post(array(
            'post_title'   => 'ورود با موبایل',
            'post_name'    => 'mobile-login',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[bamero_mobile_auth]',
        ));
    }
}
register_activation_hook(__FILE__, 'bamero_mobile_auth_activate');


/** AUTH-04: disable all WooCommerce customer-facing emails */
function bamero_disable_customer_emails($enabled, $order = null) {
    return false;
}
$bamero_email_ids = array(
    'woocommerce_email_enabled_customer_completed_order',
    'woocommerce_email_enabled_customer_processing_order',
    'woocommerce_email_enabled_customer_on_hold_order',
    'woocommerce_email_enabled_customer_refunded_order',
    'woocommerce_email_enabled_customer_invoice',
    'woocommerce_email_enabled_customer_note',
    'woocommerce_email_enabled_customer_reset_password',
    'woocommerce_email_enabled_customer_new_account',
);
foreach ($bamero_email_ids as $hook) {
    add_filter($hook, 'bamero_disable_customer_emails', 10, 2);
}

/**
 * Phone-only policy: there is no customer password, so every password-reset /
 * lost-password surface must be disabled. Customers authenticate only via OTP.
 */
function bamero_disable_password_reset() {
    // Core: block the retrieve/reset flow for OTP-only CUSTOMERS. Admins and
    // shop managers must keep a working password-recovery path.
    add_filter('allow_password_reset', function ($allow, $user_id) {
        $user = get_userdata((int) $user_id);
        if ($user && in_array('customer', (array) $user->roles, true)) {
            return false;
        }
        return $allow;
    }, 10, 2);
    add_filter('lostpassword_url', '__return_empty_string');

    // Block wp-login.php reset actions (lostpassword / rp / resetpass).
    add_action('login_init', function () {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        if (in_array($action, array('lostpassword', 'rp', 'resetpass'), true)) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    });

    // Block the WooCommerce lost-password endpoint.
    add_action('template_redirect', function () {
        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }
        global $wp;
        if (isset($wp->query_vars['lost-password'])) {
            wp_safe_redirect(wc_get_page_permalink('myaccount') ?: home_url('/'));
            exit;
        }
    }, 1);
}
add_action('init', 'bamero_disable_password_reset', 5);

/**
 * Persistent login: remembered sessions last 90 days so customers do not have
 * to re-authenticate on every visit. Session (non-remembered) cookies keep the
 * WordPress default length.
 */
add_filter('auth_cookie_expiration', function ($length, $user_id, $remember) {
    return $remember ? 90 * DAY_IN_SECONDS : $length;
}, 10, 3);