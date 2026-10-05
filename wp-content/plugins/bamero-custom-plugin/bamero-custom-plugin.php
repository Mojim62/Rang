<?php
/**
 * Plugin Name: Bamero Custom Plugin
 * Plugin URI: https://github.com/Mojig62m/rang
 * Description: Creates Bamero content pages. Theme-owned storefront presentation and product fields remain available when this plugin is inactive.
 * Version: 1.2.0
 * Author: Bamero
 * Author URI: https://github.com/Mojig62m/rang
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bamero-custom-plugin
 */

defined('ABSPATH') || exit;

define('BAMERO_PLUGIN_VERSION', '1.2.0');

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
        'privacy'      => array(
            'title'   => 'سیاست حفظ حریم خصوصی',
            'content' => '<h2>سیاست حفظ حریم خصوصی</h2>'
                . '<p>حریم خصوصی شما برای ما اهمیت دارد. این سیاست توضیح می‌دهد که فروشگاه بامرو چه اطلاعاتی را جمع‌آوری می‌کند و چگونه از آن‌ها محافظت می‌کند.</p>'
                . '<h3>اطلاعات جمع‌آوری‌شده</h3>'
                . '<ul>'
                . '<li><strong>اطلاعات سفارش:</strong> نام، شماره موبایل و آدرس ارسال، صرفاً برای پردازش و ارسال سفارش.</li>'
                . '<li><strong>شماره موبایل:</strong> به‌عنوان تنها شناسهٔ ارتباطی برای اطلاع‌رسانی وضعیت سفارش (پیامک).</li>'
                . '<li><strong>لاگ‌های فنی:</strong> خطاها و رویدادهای امنیتی برای پایداری سرویس.</li>'
                . '</ul>'
                . '<h3>چه چیزی هرگز ذخیره نمی‌شود</h3>'
                . '<p>شمارهٔ کارت بانکی، CVV2 و رمز کارت شما هرگز در سایت ذخیره نمی‌شود. پرداخت به‌صورت کامل در درگاه امن زرین‌پال انجام می‌شود و سایت تنها کد پیگیری تراکنش را دریافت می‌کند.</p>'
                . '<h3>اشتراک‌گذاری</h3>'
                . '<p>اطلاعات سفارش فقط با درگاه پرداخت زرین‌پال و شرکت حمل‌ونقل انتخابی شما به میزان لازم به اشتراک گذاشته می‌شود و به هیچ شخص ثالث دیگری فروخته نمی‌شود.</p>'
                . '<h3>حقوق شما</h3>'
                . '<p>می‌توانید هر زمان حذف یا اصلاح اطلاعات خود را از طریق شمارهٔ پشتیبانی در <a href="/contact/">صفحهٔ تماس با ما</a> درخواست کنید.</p>',
        ),
        'terms'        => array(
            'title'   => 'شرایط استفاده',
            'content' => '<h2>شرایط استفاده</h2>'
                . '<p>استفاده از فروشگاه اینترنتی بامرو به معنای پذیرش شرایط زیر است.</p>'
                . '<h3>سفارش و قیمت‌ها</h3>'
                . '<ul>'
                . '<li>قیمت‌های سایت به <strong>ریال (IRR)</strong> است و در لحظهٔ ثبت سفارش معتبر تلقی می‌شود.</li>'
                . '<li>در صورت اتمام موجودی یا خطای قیمت، سفارش لغو و مبلغ پرداختی طی ۷۲ ساعت کاری به همان درگاه بازگردانده می‌شود.</li>'
                . '<li>اعتبار کد تخفیف فقط در بازهٔ اعلام‌شده و برای هر سفارش یک بار است.</li>'
                . '</ul>'
                . '<h3>ارسال و تحویل</h3>'
                . '<p>زمان ارسال بر اساس روش حمل انتخابی و آدرس شما محاسبه می‌شود. آسیب‌دیدگی کالا باید حداکثر تا ۴۸ ساعت پس از تحویل از طریق شمارهٔ پشتیبانی اعلام شود.</p>'
                . '<h3>استرداد</h3>'
                . '<p>محصولات رنگ و شیمیایی در صورت باز شدن درپوش قابل استرداد نیستند؛ مگر ایراد از کیفیت تولید باشد.</p>'
                . '<h3>مجوز و اعتماد</h3>'
                . '<p>این فروشگاه دارای نماد اعتماد الکترونیکی (اینماد) است و جزئیات آن در صفحهٔ اصلی قابل استعلام است.</p>',
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