<?php
/**
 * Bamero Theme — Helpers
 *
 * Pure helper functions: digit conversion, price formatting and
 * owner-configurable contact details. No hooks in this file.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

/**
 * Convert Western digits to Persian digits.
 */
function bamero_persian_digits($str) {
    $en = array('0','1','2','3','4','5','6','7','8','9');
    $fa = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
    return str_replace($en, $fa, (string) $str);
}

/**
 * Format product price with Persian digits (تومان یا ریال بر اساس ارز فعال).
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

