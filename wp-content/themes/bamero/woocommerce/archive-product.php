<?php
/**
 * Shop archive — UI-19..22, UI-16..18
 *
 * @package Bamero
 */
defined('ABSPATH') || exit;

get_header('shop');

$product_categories = array();
if (taxonomy_exists('product_cat')) {
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'number'     => 20,
    ));
    if (!is_wp_error($terms)) {
        $product_categories = $terms;
    }
}
?>
<section class="page-hero" aria-label="<?php echo esc_attr__('سربرگ فروشگاه', 'bamero'); ?>">
    <h1><?php echo esc_html__('فروشگاه رنگ و ابزار', 'bamero'); ?></h1>
</section>
<nav class="breadcrumb-bar" aria-label="<?php echo esc_attr__('مسیر صفحه', 'bamero'); ?>">
    <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('خانه', 'bamero'); ?></a>
    <span class="sep">/</span>
    <span><?php echo esc_html__('فروشگاه', 'bamero'); ?></span>
</nav>

<main id="main-content" class="shop-page" tabindex="-1">
    <div class="shop-layout">
        <div class="shop-products">
            <?php if (woocommerce_product_loop()) : ?>
                <?php do_action('woocommerce_before_shop_loop'); ?>
                <?php woocommerce_product_loop_start(); ?>
                <?php while (have_posts()) : the_post(); ?>
                    <?php do_action('woocommerce_shop_loop'); ?>
                    <?php wc_get_template_part('content', 'product'); ?>
                <?php endwhile; ?>
                <?php woocommerce_product_loop_end(); ?>
                <?php do_action('woocommerce_after_shop_loop'); ?>
            <?php else : ?>
                <?php do_action('woocommerce_no_products_found'); ?>
                <div class="shop-empty" role="status">
                    <h2><?php echo esc_html__('هنوز محصولی منتشر نشده است', 'bamero'); ?></h2>
                    <p><?php echo esc_html__('پس از افزودن محصولات در ووکامرس، کاتالوگ اینجا نمایش داده می‌شود.', 'bamero'); ?></p>
                    <a class="button" href="<?php echo esc_url(home_url('/consultation/')); ?>"><?php echo esc_html__('درخواست مشاوره تخصصی', 'bamero'); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <aside class="shop-sidebar" aria-label="<?php echo esc_attr__('فیلتر و دسته‌بندی', 'bamero'); ?>">
            <div class="sidebar-block">
                <h2 class="sidebar-title"><?php echo esc_html__('دسته‌بندی محصولات', 'bamero'); ?></h2>
                <?php if (!empty($product_categories)) : ?>
                    <ul class="category-list">
                        <?php foreach ($product_categories as $cat) : ?>
                            <li>
                                <a href="<?php echo esc_url(get_term_link($cat)); ?>">
                                    <span><?php echo esc_html($cat->name); ?></span>
                                    <span class="count"><?php echo esc_html(bamero_persian_digits((string) $cat->count)); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="category-empty"><?php echo esc_html__('هنوز دسته‌بندی محصولی ایجاد نشده است.', 'bamero'); ?></p>
                <?php endif; ?>
            </div>

            <div class="sidebar-block quote-list-block">
                <h2 class="sidebar-title"><?php echo esc_html__('لیست استعلام', 'bamero'); ?></h2>
                <svg width="64" height="64" viewBox="0 0 64 64" aria-hidden="true" style="margin:12px auto;display:block;opacity:.5">
                    <rect x="18" y="22" width="28" height="30" rx="4" fill="#edf2f7" stroke="#a0aec0"/>
                    <path d="M24 22v-4a8 8 0 0 1 16 0v4" fill="none" stroke="#a0aec0" stroke-width="2"/>
                    <circle cx="32" cy="38" r="6" fill="#e53e3e" opacity=".4"/>
                </svg>
                <p class="quote-empty"><?php echo esc_html__('لیست استعلام شما خالی است', 'bamero'); ?></p>
                <a href="<?php echo esc_url(home_url('/consultation/')); ?>" class="button-block"><?php echo esc_html__('شروع استعلام', 'bamero'); ?></a>
            </div>
        </aside>
    </div>
</main>

<?php
$bamero_whatsapp_float = get_theme_mod('bamero_whatsapp_url', '');
if ($bamero_whatsapp_float === '' && function_exists('bamero_whatsapp_default')) {
    $bamero_whatsapp_float = bamero_whatsapp_default();
}
if ($bamero_whatsapp_float) :
?>
<a href="<?php echo esc_url($bamero_whatsapp_float); ?>" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr__('گفتگو در واتساپ', 'bamero'); ?>">
    <span aria-hidden="true">💬</span>
</a>
<?php endif; ?>
<?php get_footer('shop'); ?>