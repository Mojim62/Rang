<?php
/**
 * Footer UI-43..46
 *
 * @package Bamero
 */
defined('ABSPATH') || exit;

$phone   = bamero_phone_display();
$address = bamero_address_display();
?>
<footer class="site-footer">
    <div class="footer-grid">
        <div>
            <h3><?php echo esc_html__('بامرو', 'bamero'); ?></h3>
            <p><?php echo esc_html__('رنگ و چسب بامرو — تأمین رنگ ساختمانی، صنعتی، ضدآب و چسب با مشاوره فنی در اصفهان.', 'bamero'); ?></p>
        </div>
        <div>
            <h3><?php echo esc_html__('دسترسی سریع', 'bamero'); ?></h3>
            <ul>
                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('خانه', 'bamero'); ?></a></li>
                <li><a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>"><?php echo esc_html__('فروشگاه', 'bamero'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/about/')); ?>"><?php echo esc_html__('درباره ما', 'bamero'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php echo esc_html__('تماس با ما', 'bamero'); ?></a></li>
            </ul>
        </div>
        <div>
            <h3><?php echo esc_html__('تماس', 'bamero'); ?></h3>
            <ul>
                <li><?php echo esc_html__('تلفن: ', 'bamero') . esc_html($phone); ?></li>
                <li><?php echo esc_html($address); ?></li>
            </ul>
        </div>
        <div class="footer-support">
            <h3><?php echo esc_html__('پشتیبانی موبایلی', 'bamero'); ?></h3>
            <p><?php echo esc_html__('برای پیگیری سفارش یا دریافت مشاوره با شمارهٔ فروشگاه تماس بگیرید.', 'bamero'); ?></p>
            <a class="footer-support-link" href="tel:<?php echo esc_attr(bamero_phone_e164()); ?>"><?php echo esc_html(bamero_phone_display()); ?></a>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html__('بامرو — همه حقوق محفوظ است.', 'bamero'); ?></p>
    </div>
</footer>
<a href="#main-content" class="scroll-to-top" aria-label="<?php echo esc_attr__('بازگشت به بالای صفحه', 'bamero'); ?>">
    <i class="fas fa-chevron-up" aria-hidden="true"></i>
</a>
<?php wp_footer(); ?>
</body>
</html>
