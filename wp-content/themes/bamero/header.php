<?php
/**
 * Header — RTL top bar + sticky nav (UI-07..15)
 *
 * @package Bamero
 */
defined('ABSPATH') || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" href="<?php echo esc_url(BAMERO_THEME_DIR . '/images/favicon.svg'); ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?php echo esc_url(BAMERO_THEME_DIR . '/images/logo.svg'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class('rtl'); ?>>
<?php wp_body_open(); ?>

<div class="header-top-bar">
    <div class="container top-bar-inner">
        <div class="top-bar-contact">
            <a href="tel:<?php echo esc_attr(bamero_phone_e164()); ?>" class="top-phone">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.3 1.1L6.6 10.8z"/></svg>
                <?php echo esc_html(bamero_phone_display()); ?>
            </a>
        </div>
        <?php
        $bamero_instagram = get_theme_mod('bamero_instagram_url', '');
        $bamero_telegram  = get_theme_mod('bamero_telegram_url', '');
        $bamero_whatsapp  = get_theme_mod('bamero_whatsapp_url', '');
        if ($bamero_whatsapp === '' && function_exists('bamero_whatsapp_default')) {
            $bamero_whatsapp = bamero_whatsapp_default();
        }
        if ($bamero_instagram || $bamero_telegram || $bamero_whatsapp) :
        ?>
        <div class="top-bar-social" aria-label="<?php echo esc_attr__('شبکه‌های اجتماعی', 'bamero'); ?>">
            <?php if ($bamero_instagram) : ?>
            <a href="<?php echo esc_url($bamero_instagram); ?>" target="_blank" rel="noopener noreferrer" aria-label="اینستاگرام">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm6.5-.9a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($bamero_telegram) : ?>
            <a href="<?php echo esc_url($bamero_telegram); ?>" target="_blank" rel="noopener noreferrer" aria-label="تلگرام">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.8 14.6 9.5 19c.5 0 .7-.2 1-.5l2.4-2.3 5 3.7c.9.5 1.6.2 1.8-.9L22.9 4c.3-1.3-.5-1.8-1.4-1.5L2.3 9.2C1 9.7 1 10.4 2 10.7l5.3 1.7 12.3-7.7c.6-.4 1.1-.2.7.2L9.8 14.6z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($bamero_whatsapp) : ?>
            <a href="<?php echo esc_url($bamero_whatsapp); ?>" target="_blank" rel="noopener noreferrer" aria-label="واتساپ">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.7 14.9L2 22l5.3-1.4A10 10 0 1 0 12 2zm5.6 14.3c-.2.7-1.3 1.2-2.1 1.4-.6.1-1.3.2-3.8-.8-3.1-1.3-5.1-4.5-5.3-4.7-.2-.2-1.5-2-1.5-3.8s1-2.7 1.3-3.1c.3-.3.7-.4 1-.4h.7c.2 0 .5 0 .7.6.2.7.8 2.3.9 2.5.1.2.1.4 0 .6-.1.2-.2.4-.3.5-.2.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.2 1.3 2.5 1.5.3.1.5.1.7-.1.2-.2.8-.9 1-1.2.2-.3.4-.2.7-.1.3.1 1.9.9 2.2 1.1.3.2.5.3.6.4.1.2.1.9-.1 1.6z"/></svg>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<header class="site-header">
    <div class="container header-inner">
        <div class="logo">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo-link" aria-label="بامرو">
                <img src="<?php echo esc_url(BAMERO_THEME_DIR . '/images/logo.svg'); ?>"
                     alt="بامرو"
                     class="site-logo-img logo-mark"
                     width="48"
                     height="48"
                     decoding="async" />
                <span class="site-logo-text">
                    <span class="logo-name">بامرو</span>
                    <span class="logo-tag">رنگ · چسب · پوشش</span>
                </span>
            </a>
        </div>

        <button type="button" class="mobile-menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php echo esc_attr__('منو', 'bamero'); ?>">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-navigation" id="primary-menu" aria-label="<?php echo esc_attr__('منوی اصلی', 'bamero'); ?>">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => '',
                    'fallback_cb'    => false,
                    'depth'          => 2,
                ));
            } else {
                $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
                ?>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('خانه', 'bamero'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/about/')); ?>"><?php echo esc_html__('درباره ما', 'bamero'); ?></a></li>
                    <li class="current-menu-item"><a href="<?php echo esc_url($shop_url); ?>" aria-current="page"><?php echo esc_html__('فروشگاه', 'bamero'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php echo esc_html__('تماس', 'bamero'); ?></a></li>
                </ul>
                <?php
            }
            ?>
        </nav>

        <form class="header-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="screen-reader-text" for="bamero-header-search"><?php echo esc_html__('جستجوی محصولات', 'bamero'); ?></label>
            <input id="bamero-header-search" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="جستجوی محصول یا کد رنگ" />
            <input type="hidden" name="post_type" value="product" />
            <button type="submit" aria-label="<?php echo esc_attr__('جستجو', 'bamero'); ?>">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
            </button>
        </form>

        <div class="header-actions">
            <a href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); ?>" class="mini-cart-link bamero-mini-cart" aria-label="<?php echo esc_attr__('سبد خرید', 'bamero'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="cart-count"><?php echo (function_exists('WC') && WC()->cart) ? esc_html(bamero_persian_digits((string) WC()->cart->get_cart_contents_count())) : '۰'; ?></span>
            </a>
        </div>
    </div>
</header>
<div class="nav-backdrop" id="nav-backdrop" hidden></div>

<?php if (!is_admin()) : ?>
<div class="bamero-mobile-search" role="region" aria-label="<?php echo esc_attr__('جستجوی محصولات', 'bamero'); ?>">
    <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <label class="screen-reader-text" for="bamero-mobile-search-input"><?php echo esc_html__('جستجوی محصولات', 'bamero'); ?></label>
        <input id="bamero-mobile-search-input" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="جستجوی محصول یا کد رنگ" enterkeyhint="search" autocomplete="off" />
        <input type="hidden" name="post_type" value="product" />
        <button type="submit" aria-label="<?php echo esc_attr__('جستجو', 'bamero'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
        </button>
    </form>
</div>
<?php endif; ?>
