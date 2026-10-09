/**
 * Bamero Theme — Main JavaScript
 *
 * go-live UI/UX cleanup 2026-10 (audit: fix/ui-ux-golive-audit-2026-10):
 *  - removed dead selectors: .header-main-wrap, .scroll-to-top (now server-rendered
 *    in footer.php), .filter-chips, .skip-to-content, .woocommerce-filters,
 *    .mobile-tabs, .keyboard-focus, .stock-status, .product-color-code JS styling
 *  - removed broken AJAX fragments call (wrong endpoint/nonce param; WooCommerce's
 *    wc-cart-fragments — now enqueued on all commerce surfaces — owns this)
 *  - removed jQuery UI slider init ($.fn.slider is never loaded; the WooCommerce
 *    price-filter widget ships its own script)
 *  - removed custom image modal (it blocked clicks on product-card links);
 *    WooCommerce's native gallery trigger is restored in css/woocommerce.css
 *  - removed read-more/less (destroyed description HTML), JS form validation
 *    (native + WooCommerce validation own this), JS lazy-load (WordPress core),
 *    ARIA role="button" mutation and duplicated aria-labels (anti-patterns)
 * Kept behaviors: mobile drawer, checkout error scroll, category accordion,
 * numeric quantity guard, coverage calculator.
 */

jQuery(function ($) {
    'use strict';

    /* ===== Mobile drawer menu (UI-15) ===== */
    var $toggle = $('.mobile-menu-toggle');
    var $nav = $('.main-navigation');
    var $backdrop = $('#nav-backdrop');
    if (!$backdrop.length) {
        $backdrop = $('<div class="nav-backdrop" id="nav-backdrop" hidden></div>').appendTo('body');
    }

    function closeMenu() {
        $nav.removeClass('is-open');
        $toggle.removeClass('is-open').attr('aria-expanded', 'false');
        $backdrop.removeClass('is-open').attr('hidden', true);
        $('body').css('overflow', '');
    }
    function openMenu() {
        $nav.addClass('is-open');
        $toggle.addClass('is-open').attr('aria-expanded', 'true');
        $backdrop.addClass('is-open').removeAttr('hidden');
        $('body').css('overflow', 'hidden');
    }

    $toggle.on('click', function () {
        if ($nav.hasClass('is-open')) { closeMenu(); } else { openMenu(); }
    });
    $backdrop.on('click', closeMenu);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { closeMenu(); }
    });
    $nav.on('click', 'a', function () {
        if (window.matchMedia('(max-width: 767px)').matches) { closeMenu(); }
    });
    var desktopMq = window.matchMedia('(min-width: 768px)');
    if (typeof desktopMq.addEventListener === 'function') {
        desktopMq.addEventListener('change', function (e) {
            if (e.matches) { closeMenu(); }
        });
    }

    /* ===== AJAX add-to-cart: notices and cart fragments are owned by
       WooCommerce core scripts. Only ensure the drawer closes. ===== */
    $(document.body).on('added_to_cart', function () {
        closeMenu();
    });

    /* ===== Checkout: bring the first validation error into view ===== */
    $(document.body).on('checkout_error', function () {
        var $firstError = $('.woocommerce-invalid').first();
        if ($firstError.length) {
            $('html, body').animate({ scrollTop: $firstError.offset().top - 100 }, 500);
        }
    });

    /* ===== Sidebar category accordion (widget markup) ===== */
    $('.toggle-children').on('click', function () {
        var $this = $(this);
        var $children = $this.closest('li').find('.children');
        var isExpanded = $this.attr('aria-expanded') === 'true';
        $this.attr('aria-expanded', String(!isExpanded));
        $children.attr('aria-hidden', String(isExpanded));
        $children.stop(true).slideToggle(180);
    });

    /* ===== Quantity inputs: numeric keys only ===== */
    $('.quantity input').on('keydown', function (e) {
        if (e.key === '+' || e.key === '-' || e.key === 'e') {
            e.preventDefault();
        }
    });

    /* ===== Coverage calculator (markup emitted by functions.php) ===== */
    $('#bamero-area').on('input', function () {
        var area = parseFloat($(this).val()) || 0;
        var coverage = parseFloat($('#bamero-coverage-result').data('coverage')) || 0;
        var result = $('#bamero-coverage-result');
        if (area <= 0 || coverage <= 0) {
            result.text(coverage > 0 ? 'مساحت را وارد کنید.' : 'پوشش‌دهی محصول ثبت نشده است.');
            return;
        }
        result.text('مقدار تقریبی: ' + (area / coverage * 1.1).toFixed(1) + ' لیتر با ضریب اطمینان ۱۰٪');
    });
});
