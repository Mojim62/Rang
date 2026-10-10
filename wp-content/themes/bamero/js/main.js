/**
 * Bamero Theme — Main JavaScript (vanilla-first)
 *
 * Architecture 2026-10 (go-live hardening):
 *  - Core UI (mobile drawer, quantity guard, coverage calculator) is plain
 *    DOM JavaScript and runs on every page — no jQuery required.
 *  - jQuery is a script dependency only on commerce surfaces (inc/setup.php),
 *    where WooCommerce loads it. The WooCommerce-only behaviors below
 *    (added_to_cart drawer close, checkout error scroll, category accordion)
 *    degrade silently when jQuery is absent.
 * Retained decisions from the UI/UX audit (fix/ui-ux-golive-audit-2026-10):
 * dead selectors, broken AJAX fragments, jQuery-UI slider init, image modal,
 * read-more/less, JS form validation, JS lazy-load and ARIA mutations stay
 * removed; WooCommerce core scripts own those behaviors.
 */
(function () {
    'use strict';

    /* ===== Mobile drawer menu (UI-15) ===== */
    var toggle = document.querySelector('.mobile-menu-toggle');
    var nav = document.querySelector('.main-navigation');
    var backdrop = document.getElementById('nav-backdrop');

    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'nav-backdrop';
        backdrop.id = 'nav-backdrop';
        backdrop.hidden = true;
        document.body.appendChild(backdrop);
    }

    function closeMenu() {
        if (nav) { nav.classList.remove('is-open'); }
        if (toggle) {
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
        backdrop.classList.remove('is-open');
        backdrop.hidden = true;
        document.body.style.overflow = '';
    }
    function openMenu() {
        if (nav) { nav.classList.add('is-open'); }
        if (toggle) {
            toggle.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
        backdrop.classList.add('is-open');
        backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (nav && nav.classList.contains('is-open')) { closeMenu(); } else { openMenu(); }
        });
    }
    backdrop.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeMenu(); }
    });
    if (nav) {
        nav.addEventListener('click', function (e) {
            if (e.target instanceof Element && e.target.closest('a') && window.matchMedia('(max-width: 767px)').matches) {
                closeMenu();
            }
        });
    }
    var desktopMq = window.matchMedia('(min-width: 768px)');
    if (typeof desktopMq.addEventListener === 'function') {
        desktopMq.addEventListener('change', function (e) {
            if (e.matches) { closeMenu(); }
        });
    }

    /* ===== Quantity inputs: numeric keys only (delegated — survives AJAX) ===== */
    document.addEventListener('keydown', function (e) {
        if (e.key !== '+' && e.key !== '-' && e.key !== 'e') { return; }
        if (e.target instanceof Element && e.target.closest('.quantity input')) {
            e.preventDefault();
        }
    });

    /* ===== Coverage calculator (markup emitted by inc/woocommerce.php) ===== */
    var areaInput = document.getElementById('bamero-area');
    var resultEl = document.getElementById('bamero-coverage-result');
    if (areaInput && resultEl) {
        areaInput.addEventListener('input', function () {
            var area = parseFloat(areaInput.value) || 0;
            var coverage = parseFloat(resultEl.getAttribute('data-coverage')) || 0;
            if (area <= 0 || coverage <= 0) {
                resultEl.textContent = coverage > 0 ? 'مساحت را وارد کنید.' : 'پوشش‌دهی محصول ثبت نشده است.';
                return;
            }
            resultEl.textContent = 'مقدار تقریبی: ' + (area / coverage * 1.1).toFixed(1) + ' لیتر با ضریب اطمینان ۱۰٪';
        });
    }

    /* ===== WooCommerce extras (jQuery present on commerce surfaces only) ===== */
    if (window.jQuery) {
        window.jQuery(function ($) {
            /* AJAX add-to-cart: notices and cart fragments are owned by
               WooCommerce core scripts. Only ensure the drawer closes. */
            $(document.body).on('added_to_cart', closeMenu);

            /* Checkout: bring the first validation error into view */
            $(document.body).on('checkout_error', function () {
                var $firstError = $('.woocommerce-invalid').first();
                if ($firstError.length) {
                    $('html, body').animate({ scrollTop: $firstError.offset().top - 100 }, 500);
                }
            });

            /* Sidebar category accordion (widget markup) */
            $(document).on('click', '.toggle-children', function () {
                var $this = $(this);
                var $children = $this.closest('li').find('.children');
                var isExpanded = $this.attr('aria-expanded') === 'true';
                $this.attr('aria-expanded', String(!isExpanded));
                $children.attr('aria-hidden', String(isExpanded));
                $children.stop(true).slideToggle(180);
            });
        });
    }
})();
