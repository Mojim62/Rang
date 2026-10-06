<?php
/**
 * Search form — هماهنگ با استایل جستجوی تم (مخصوص فروشگاه، RTL)
 *
 * @package Bamero
 */
defined('ABSPATH') || exit;

$bamero_search_id = 'bamero-search-input-' . wp_unique_id('s-');
?>
<form role="search" method="get" class="bamero-searchform" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="<?php echo esc_attr($bamero_search_id); ?>"><?php echo esc_html__('جستجوی محصولات', 'bamero'); ?></label>
    <input type="search" id="<?php echo esc_attr($bamero_search_id); ?>" class="search-field" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php echo esc_attr__('جستجوی محصول یا کد رنگ', 'bamero'); ?>" />
    <input type="hidden" name="post_type" value="product" />
    <button type="submit" class="search-submit" aria-label="<?php echo esc_attr__('جستجو', 'bamero'); ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
    </button>
</form>