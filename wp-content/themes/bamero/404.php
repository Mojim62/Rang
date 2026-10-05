<?php
/**
 * 404 fallback template for the Bamero theme.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

get_header();
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
if (!$shop_url) {
    $shop_url = home_url('/');
}
?>

<main id="main-content" class="rtl">
    <div class="error-404 container text-center">
        <h1>۴۰۴</h1>
        <h2>صفحه یافت نشد</h2>
        <p>متأسفانه صفحه‌ای که به دنبال آن هستید، یافت نشد.</p>

        <div class="error-actions margin-bottom">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="button">بازگشت به صفحه اصلی</a>
            <a href="<?php echo esc_url($shop_url); ?>" class="button">بازگشت به فروشگاه</a>
        </div>

        <div class="error-search margin-bottom">
            <h3>جستجو در سایت</h3>
            <?php get_search_form(); ?>
        </div>
    </div>
</main>

<?php
get_footer();
