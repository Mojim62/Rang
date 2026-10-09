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
    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.7 14.9L2 22l5.3-1.4A10 10 0 1 0 12 2zm5.6 14.3c-.2.7-1.3 1.2-2.1 1.4-.6.1-1.3.2-3.8-.8-3.1-1.3-5.1-4.5-5.3-4.7-.2-.2-1.5-2-1.5-3.8s1-2.7 1.3-3.1c.3-.3.7-.4 1-.4h.7c.2 0 .5 0 .7.6.2.7.8 2.3.9 2.5.1.2.1.4 0 .6-.1.2-.2.4-.3.5-.2.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.2 1.3 2.5 1.5.3.1.5.1.7-.1.2-.2.8-.9 1-1.2.2-.3.4-.2.7-.1.3.1 1.9.9 2.2 1.1.3.2.5.3.6.4.1.2.1.9-.1 1.6z"/></svg>
</a>
<?php endif; ?>
<?php get_footer('shop'); ?>
