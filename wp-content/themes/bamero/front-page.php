<?php
/**
 * Bamero storefront homepage — production data only.
 * The page renders published WooCommerce records; no hard-coded product cards.
 */
defined('ABSPATH') || exit;
get_header();
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$consultation = get_page_by_path('consultation');
$consultation_url = $consultation ? get_permalink($consultation) : home_url('/contact/');
$products = function_exists('wc_get_products') ? wc_get_products(array('status'=>'publish','limit'=>10,'orderby'=>'date','order'=>'DESC','return'=>'objects')) : array();
$categories = function_exists('get_terms') ? get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'number'=>6,'orderby'=>'count','order'=>'DESC')) : array();
if (is_wp_error($categories)) $categories = array();
?>
<main id="main-content" class="bamero-home" tabindex="-1">
    <section class="bamero-hero container" aria-labelledby="bamero-hero-title">
        <div class="bamero-hero-copy">
            <p class="bamero-kicker">فروش تخصصی رنگ، چسب و پوشش‌های حرفه‌ای</p>
            <h1 id="bamero-hero-title">برای هر سطح، یک راه‌حل ماندگار</h1>
            <p class="bamero-hero-lead">محصول مناسب پروژه‌تان را بر اساس سطح، حجم و کاربرد پیدا کنید؛ با مشخصات فنی روشن و پشتیبانی موبایلی بامرو.</p>
            <div class="bamero-hero-actions">
                <a class="button bamero-button-primary" href="<?php echo esc_url($shop_url); ?>">مشاهده کاتالوگ</a>
                <a class="button bamero-button-quiet" href="<?php echo esc_url($consultation_url); ?>">مشاوره انتخاب محصول</a>
            </div>
            <div class="bamero-hero-proof" aria-label="مزیت‌های خرید">
                <span><strong>ارسال مطمئن</strong><small>بسته‌بندی مناسب پروژه</small></span>
                <span><strong>انتخاب فنی</strong><small>کد RAL و پوشش‌دهی</small></span>
                <span><strong>پشتیبانی سریع</strong><small>از طریق شماره موبایل</small></span>
            </div>
        </div>
        <div class="bamero-hero-art" aria-hidden="true"><div class="bamero-paint-orbit orbit-one"></div><div class="bamero-paint-orbit orbit-two"></div><div class="bamero-paint-can"><span>BAMERO</span><small>professional finish</small></div></div>
    </section>

    <section class="bamero-category-strip container" aria-labelledby="category-title">
        <div class="bamero-section-heading"><div><p class="bamero-eyebrow">شروع سریع</p><h2 id="category-title">از دسته‌بندی درست شروع کنید</h2></div><a href="<?php echo esc_url($shop_url); ?>">همه محصولات <span aria-hidden="true">←</span></a></div>
        <div class="bamero-category-grid">
            <?php if ($categories) : foreach ($categories as $category) : $term_link = get_term_link($category); if (is_wp_error($term_link)) continue; ?>
                <a class="bamero-category-card" href="<?php echo esc_url($term_link); ?>"><span class="bamero-category-icon" aria-hidden="true"><?php echo esc_html(mb_substr($category->name, 0, 1)); ?></span><span><strong><?php echo esc_html($category->name); ?></strong><small><?php echo esc_html(number_format_i18n($category->count)); ?> محصول</small></span><span class="bamero-arrow" aria-hidden="true">←</span></a>
            <?php endforeach; else : ?><a class="bamero-category-card" href="<?php echo esc_url($shop_url); ?>"><span class="bamero-category-icon">ب</span><span><strong>کاتالوگ محصولات</strong><small>مشاهده محصولات موجود</small></span><span class="bamero-arrow" aria-hidden="true">←</span></a><?php endif; ?>
        </div>
    </section>

    <section class="bamero-catalog container" aria-labelledby="catalog-title">
        <div class="bamero-section-heading"><div><p class="bamero-eyebrow">کاتالوگ بامرو</p><h2 id="catalog-title">محصولات منتخب برای پروژه‌های واقعی</h2></div><a href="<?php echo esc_url($shop_url); ?>">مشاهده همه <span aria-hidden="true">←</span></a></div>
        <div class="bamero-product-grid">
            <?php if ($products) : foreach ($products as $product) : $GLOBALS['product'] = $product; $product_id = $product->get_id(); $image = $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') : ''; $brand = get_post_meta($product_id, '_product_brand', true); ?>
                <article class="bamero-product-card">
                    <a class="bamero-product-media" href="<?php echo esc_url(get_permalink($product_id)); ?>" aria-label="مشاهده <?php echo esc_attr($product->get_name()); ?>">
                        <?php if ($product->is_on_sale()) : ?><span class="bamero-sale-badge">پیشنهاد ویژه</span><?php endif; ?>
                        <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" loading="lazy" width="420" height="315"><?php else : ?><div class="bamero-product-art" style="--product-accent: #dbe6ef" aria-hidden="true"><span>BAMERO</span></div><?php endif; ?>
                    </a>
                    <div class="bamero-product-body"><div class="bamero-product-meta"><span><?php echo esc_html($brand ?: 'انتخاب بامرو'); ?></span></div><h3><a href="<?php echo esc_url(get_permalink($product_id)); ?>"><?php echo esc_html($product->get_name()); ?></a></h3><div class="bamero-product-bottom"><span class="bamero-price"><?php echo wp_kses_post($product->get_price_html()); ?></span><?php woocommerce_template_loop_add_to_cart(array('product'=>$product)); ?></div></div>
                </article>
            <?php endforeach; else : ?><div class="bamero-empty-state"><h3>کاتالوگ در حال آماده‌سازی است</h3><p>پس از فعال‌سازی ووکامرس و اجرای provisioning، محصولات واقعی اینجا نمایش داده می‌شوند.</p><a class="button" href="<?php echo esc_url($consultation_url); ?>">تماس با پشتیبانی</a></div><?php endif; ?>
        </div>
    </section>

    <section class="bamero-service-panel container" aria-labelledby="service-title"><div><p class="bamero-eyebrow">انتخاب مطمئن‌تر</p><h2 id="service-title">قبل از خرید، مشخصات پروژه‌تان را با ما چک کنید</h2><p>اگر درباره رنگ، میزان مصرف یا سازگاری سطح مطمئن نیستید، اطلاعات پروژه را بفرستید تا مسیر خرید شما کوتاه‌تر و دقیق‌تر شود.</p></div><a class="button bamero-button-primary" href="<?php echo esc_url($consultation_url); ?>">درخواست مشاوره</a></section>
</main>
<?php get_footer(); ?>