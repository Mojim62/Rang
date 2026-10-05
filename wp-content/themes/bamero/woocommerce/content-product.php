<?php
/**
 * Product card — UI-23..33
 *
 * @package Bamero
 */
defined('ABSPATH') || exit;

global $product;

if (!$product instanceof WC_Product || !$product->is_visible()) {
    return;
}

$badge = '';
if ($product->is_on_sale()) {
    $badge = 'تخفیف ویژه';
} elseif ((time() - get_post_time('U', true, $product->get_id())) < WEEK_IN_SECONDS * 2) {
    $badge = 'جدید';
}

$cats = wc_get_product_category_list($product->get_id(), '، ');
$img_id = $product->get_image_id();
$swatch = get_post_meta($product->get_id(), '_product_color_code', true);
$ral_code = get_post_meta($product->get_id(), '_bamero_ral_code', true);
if (!is_string($swatch) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $swatch)) {
    $swatch = '#F8F9FA';
}
$swatch_label = $ral_code ? $ral_code : ($swatch !== '#F8F9FA' ? $swatch : 'کد رنگ ثبت نشده');
?>
<li <?php wc_product_class('product-card paint-can', $product); ?>>
    <?php if ($badge) : ?>
        <span class="product-badge"><?php echo esc_html($badge); ?></span>
    <?php endif; ?>

    <div class="product-image">
        <a href="<?php echo esc_url($product->get_permalink()); ?>" aria-label="<?php echo esc_attr($product->get_name()); ?>">
            <?php if ($img_id) : ?>
                <?php echo $product->get_image('woocommerce_thumbnail', array('loading' => 'lazy', 'decoding' => 'async', 'alt' => $product->get_name())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <div class="paint-swatch" style="background-color:<?php echo esc_attr($swatch); ?>" role="img" aria-label="<?php echo esc_attr(sprintf('رنگ %s کد %s', $product->get_name(), $swatch_label)); ?>">
                    <span><?php echo esc_html($swatch_label); ?></span>
                </div>
            <?php endif; ?>
        </a>
        <a class="product-quick-view" href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html__('مشاهده سریع', 'bamero'); ?></a>
    </div>

    <div class="product-info">
        <h2 class="product-title woocommerce-loop-product__title">
            <a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a>
        </h2>
        <?php if ($cats) : ?>
            <div class="product-category"><?php echo wp_kses_post($cats); ?></div>
        <?php endif; ?>
        <div class="product-availability" aria-label="وضعیت موجودی">
            <?php echo $product->is_in_stock() ? esc_html__('موجود', 'bamero') : esc_html__('ناموجود', 'bamero'); ?>
        </div>
        <div class="product-price-row">
            <span class="price"><?php echo wp_kses_post(bamero_format_price_html($product)); ?></span>
            <?php
            echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'woocommerce_loop_add_to_cart_link',
                sprintf(
                    '<a href="%s" data-quantity="1" class="button add_to_cart_button product_type_%s add-to-cart-circle %s" data-product_id="%s" data-product_sku="%s" aria-label="%s" rel="nofollow"></a>',
                    esc_url($product->add_to_cart_url()),
                    esc_attr($product->get_type()),
                    $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
                    esc_attr((string) $product->get_id()),
                    esc_attr($product->get_sku()),
                    esc_attr(sprintf(__('افزودن %s به سبد', 'bamero'), $product->get_name()))
                ),
                $product
            );
            ?>
        </div>
    </div>
</li>