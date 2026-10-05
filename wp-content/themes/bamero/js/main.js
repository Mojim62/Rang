/**
 * Bamero Theme Main JavaScript File
 * Description: اسکریپت‌های اصلی برای تم بامرو
 */

jQuery(document).ready(function($) {
    // ===== MOBILE MENU TOGGLE =====
    // Mobile drawer menu UI-15
    var $toggle = $('.mobile-menu-toggle');
    var $nav = $('.main-navigation');
    var $backdrop = $('#nav-backdrop');
    if (!$backdrop.length) {
        $backdrop = $('<div class="nav-backdrop" id="nav-backdrop" hidden></div>').appendTo('body');
    }
    function closeMenu() {
        $nav.removeClass('is-open');
        $backdrop.removeClass('is-open').attr('hidden', true);
        $toggle.attr('aria-expanded', 'false');
        $('body').css('overflow', '');
    }
    function openMenu() {
        $nav.addClass('is-open');
        $backdrop.addClass('is-open').removeAttr('hidden');
        $toggle.attr('aria-expanded', 'true');
        $('body').css('overflow', 'hidden');
    }
    $toggle.on('click', function() {
        if ($nav.hasClass('is-open')) { closeMenu(); } else { openMenu(); }
    });
    $backdrop.on('click', closeMenu);
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') { closeMenu(); }
    });


    // Close mobile menu when clicking on a link
    $('.main-navigation a').on('click', function() {
        if ($(window).width() < 768) {
            closeMenu();
        }
    });

    // Close menu on resize to desktop
    $(window).on('resize', function() {
        if ($(window).width() >= 768) {
            closeMenu();
        }
    });

    // Sticky header elevation on scroll
    var $headerWrap = $('.header-main-wrap');
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > 40) {
            $headerWrap.addClass('is-scrolled');
            $('.scroll-to-top').addClass('visible');
        } else {
            $headerWrap.removeClass('is-scrolled');
            $('.scroll-to-top').removeClass('visible');
        }
    });

    // Filter chips (visual toggle)
    $('.filter-chips').on('click', '.chip', function() {
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
    });

    $('.scroll-to-top').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({ scrollTop: 0 }, 'smooth');
    });

    // Add scroll to top button to the page
    if (!$('.scroll-to-top').length) {
        $('body').append('<a href="#" class="scroll-to-top" aria-label="برو به بالای صفحه"><i class="fas fa-chevron-up"></i></a>');
    }

    // Hover handled purely by CSS for performance (no JS class thrashing)

    // ===== PRODUCT CATEGORY TOGGLE =====
    $('.toggle-children').on('click', function() {
        var $this = $(this);
        var $children = $this.closest('li').find('.children');
        var isExpanded = $this.attr('aria-expanded') === 'true';

        $this.attr('aria-expanded', !isExpanded);
        $children.attr('aria-hidden', isExpanded);

        if (isExpanded) {
            $children.slideUp();
        } else {
            $children.slideDown();
        }
    });

    // ===== QUANTITY INPUT SPINNER =====
    $('.quantity input').on('focus', function() {
        $(this).closest('.quantity').addClass('focus');
    }).on('blur', function() {
        $(this).closest('.quantity').removeClass('focus');
    });

    // Prevent direct input for quantity
    $('.quantity input').on('keydown', function(e) {
        if (e.key === '+' || e.key === '-' || e.key === 'e') {
            e.preventDefault();
        }
    });

    // ===== ADD TO CART AJAX =====
    $(document.body).on('added_to_cart', function() {
        // Show success message
        var message = '<div class="woocommerce-message" role="alert">محصول با موفقیت به سبد خرید اضافه شد!</div>';
        $('.woocommerce-notices').html(message).fadeIn();

        // Update cart fragments
        var cartSelector = window.BAMERO_CART_SELECTOR || '.bamero-mini-cart';
        var fragments = {};
        fragments[cartSelector] = 1;

        if (typeof wc_add_to_cart_params === 'undefined') {
            return;
        }
        $.ajax({
            type: 'POST',
            url: wc_add_to_cart_params.ajax_url,
            data: {
                action: 'woocommerce_get_refreshed_fragments',
                fragments: fragments,
                nonce: wc_add_to_cart_params.nonce
            },
            success: function(response) {
                if (response.fragments) {
                    $.each(response.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });
                }
            }
        });

        // Hide message after 3 seconds
        setTimeout(function() {
            $('.woocommerce-message').fadeOut();
        }, 3000);
    });

    // ===== CHECKOUT FORM VALIDATION =====
    $(document.body).on('checkout_error', function() {
        // Scroll to the first error
        var $firstError = $('.woocommerce-invalid:first');
        if ($firstError.length) {
            $('html, body').animate({
                scrollTop: $firstError.offset().top - 100
            }, 500);
        }
    });

    // ===== PRODUCT TABS =====
    $('.woocommerce-tabs ul.tabs li a').on('click', function(e) {
        e.preventDefault();
        var $this = $(this);
        var $tab = $this.closest('li');
        var $tabs = $this.closest('ul.tabs');
        var tabId = $this.attr('href');

        // Remove active class from all tabs
        $tabs.find('li').removeClass('active');
        $tab.addClass('active');

        // Hide all panels
        $tabs.closest('.woocommerce-tabs').find('.panel').hide();

        // Show selected panel
        $(tabId).show();
    });

    // ===== PRODUCT FILTERING =====
    $('.woocommerce-filters select, .woocommerce-filters input').on('change', function() {
        var $form = $(this).closest('form');
        if ($form.length) {
            $form.submit();
        }
    });

    // ===== PRICE FILTER SLIDER =====
    if (typeof $.fn.slider === 'function') {
        $('.price_slider').each(function() {
            var $this = $(this);
            var $amount = $this.next('.price_slider_amount');
            var $inputMin = $this.find('.price_slider_min');
            var $inputMax = $this.find('.price_slider_max');

            $this.slider({
                range: true,
                min: parseFloat($inputMin.data('min')),
                max: parseFloat($inputMax.data('max')),
                values: [parseFloat($inputMin.val()), parseFloat($inputMax.val())],
                create: function() {
                    $amount.find('.from').text($this.slider('values', 0).toLocaleString('fa-IR'));
                    $amount.find('.to').text($this.slider('values', 1).toLocaleString('fa-IR'));
                },
                slide: function(event, ui) {
                    $inputMin.val(ui.values[0]);
                    $inputMax.val(ui.values[1]);
                    $amount.find('.from').text(ui.values[0].toLocaleString('fa-IR'));
                    $amount.find('.to').text(ui.values[1].toLocaleString('fa-IR'));
                },
                change: function(event, ui) {
                    $(this).closest('form').submit();
                }
            });
        });
    }

    // ===== PRODUCT COLOR CODE DISPLAY =====
    $('.product-color-code span').each(function() {
        var colorCode = $(this).text();
        var contrastColor = getContrastColor(colorCode);
        $(this).css({
            'background-color': colorCode,
            'color': contrastColor,
            'padding': '0.25rem 0.5rem',
            'border-radius': '3px',
            'display': 'inline-block',
            'font-weight': 'bold'
        });
    });

    // Calculate contrast color for text
    function getContrastColor(hexColor) {
        var r = parseInt(hexColor.substr(1, 2), 16);
        var g = parseInt(hexColor.substr(3, 2), 16);
        var b = parseInt(hexColor.substr(5, 2), 16);
        var brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#000000' : '#FFFFFF';
    }

    // ===== PRODUCT IMAGE PREVIEW MODAL =====
    $('.product-card img, .woocommerce-product-gallery__image img').on('click', function(e) {
        e.preventDefault();
        var imageUrl = $(this).attr('src');
        var imageAlt = $(this).attr('alt');

        // Create modal if it doesn't exist
        if (!$('#image-preview-modal').length) {
            var baseZ = parseInt($('.whatsapp-float').css('z-index'), 10);
            var modalZ = isNaN(baseZ) ? 9999 : baseZ + 100;
            $('body').append('\n' +
                '<div id="image-preview-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); z-index: ' + modalZ + '; justify-content: center; align-items: center;">\n' +
                '    <span id="close-preview" style="position: absolute; top: 20px; right: 20px; color: white; font-size: 30px; cursor: pointer;" aria-label="بستن">&times;</span>\n' +
                '    <img id="image-preview" style="max-width: 90%; max-height: 90%;" src="" alt="" />\n' +
                '</div>\n' +
                '');
        }

        $('#image-preview').attr({'src': imageUrl, 'alt': imageAlt});
        $('#image-preview-modal').fadeIn();
    });

    // Close image preview modal
    $(document).on('click', '#close-preview, #image-preview-modal', function(e) {
        if (e.target.id === 'image-preview-modal' || e.target.id === 'close-preview') {
            $('#image-preview-modal').fadeOut();
        }
    });

    // Prevent closing modal when clicking on the image
    $(document).on('click', '#image-preview', function(e) {
        e.stopPropagation();
    });

    // Close modal with ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#image-preview-modal').is(':visible')) {
            $('#image-preview-modal').fadeOut();
        }
    });

    // ===== READ MORE/LESS FUNCTIONALITY =====
    $('.product-description, .additional-info-content').each(function() {
        var $this = $(this);
        var fullText = $this.text();
        var words = fullText.split(' ');

        if (words.length > 50) {
            var shortText = words.slice(0, 50).join(' ') + '...';
            $this.html(shortText + '<a href="#" class="read-more">بیشتر بخوانید</a>');
            $this.append('<span class="full-text" style="display: none;">' + fullText + '</span>');
        }
    });

    // Read more link click
    $(document).on('click', '.read-more', function(e) {
        e.preventDefault();
        var $this = $(this);
        var fullText = $this.siblings('.full-text').text();
        $this.parent().html(fullText + '<a href="#" class="read-less">کمتر بخوانید</a>');
    });

    // Read less link click
    $(document).on('click', '.read-less', function(e) {
        e.preventDefault();
        var $this = $(this);
        var fullText = $this.parent().text().replace('کمتر بخوانید', '').trim();
        var words = fullText.split(' ');
        var shortText = words.slice(0, 50).join(' ') + '...';
        $this.parent().html(shortText + '<a href="#" class="read-more">بیشتر بخوانید</a>');
        $this.parent().append('<span class="full-text" style="display: none;">' + fullText + '</span>');
    });

    // ===== FORM VALIDATION =====
    $('form.woocommerce-form').on('submit', function(e) {
        var $form = $(this);
        var isValid = true;

        // Validate required fields
        $form.find('[required]').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        // Validate email format
        $form.find('input[type="email"]').each(function() {
            var email = $(this).val().trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            var $firstError = $form.find('.error:first');
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        }
    });

    // Clear error on input
    $('form.woocommerce-form input, form.woocommerce-form textarea').on('input', function() {
        $(this).removeClass('error');
    });

    // ===== STOCK STATUS DISPLAY =====
    $('.stock-status').each(function() {
        var $this = $(this);
        if ($this.hasClass('in-stock')) {
            $this.css('color', '#4CAF50');
        } else if ($this.hasClass('out-of-stock')) {
            $this.css('color', '#F44336');
        }
    });

    // ===== WHATSAPP FLOAT BUTTON =====
    $('.whatsapp-float').on('click', function(e) {
        e.preventDefault();
        var phoneNumber = $(this).attr('href').replace('https://wa.me/', '');
        window.open('https://wa.me/' + phoneNumber, '_blank');
    });

    // ===== ACCESSIBILITY IMPROVEMENTS =====
    
    // Add focus styles for keyboard navigation
    $('a, button, input, select, textarea').on('focus', function() {
        $(this).addClass('keyboard-focus');
    }).on('blur', function() {
        $(this).removeClass('keyboard-focus');
    });

    // Skip to content functionality
    $('.skip-to-content').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $(target).attr('tabindex', -1).focus();
    });

    // ===== LAZY LOAD IMAGES =====
    if ('loading' in HTMLImageElement.prototype) {
        $('img').each(function() {
            if (!$(this).attr('loading')) {
                $(this).attr('loading', 'lazy');
            }
        });
    }

    // ===== RESPONSIVE ADJUSTMENTS =====
    function handleResponsive() {
        if ($(window).width() < 768) {
            // Mobile adjustments
            $('.woocommerce-tabs ul.tabs').addClass('mobile-tabs');
        } else {
            // Desktop adjustments
            $('.woocommerce-tabs ul.tabs').removeClass('mobile-tabs');
        }
    }

    // Run on load and resize
    handleResponsive();
    $(window).on('resize', handleResponsive);

    // ===== COVERAGE CALCULATOR =====
    $('#bamero-area').on('input', function() {
        var area = parseFloat($(this).val()) || 0;
        var coverage = parseFloat($('#bamero-coverage-result').data('coverage')) || 0;
        var result = $('#bamero-coverage-result');
        if (area <= 0 || coverage <= 0) {
            result.text(coverage > 0 ? 'مساحت را وارد کنید.' : 'پوشش‌دهی محصول ثبت نشده است.');
            return;
        }
        result.text('مقدار تقریبی: ' + (area / coverage * 1.1).toFixed(1) + ' لیتر با ضریب اطمینان ۱۰٪');
    });

    // ===== INITIALIZE ALL FUNCTIONALITY =====
    function initializeBamero() {
        // Initialize all components
        handleResponsive();

        // Add ARIA attributes for accessibility
        $('a[href^="#"]').each(function() {
            var $this = $(this);
            if (!$this.attr('aria-label') && $this.text().trim()) {
                $this.attr('aria-label', 'برو به ' + $this.text().trim());
            }
        });

        // Add role attributes
        $('.button, button, input[type="submit"], input[type="button"]').attr('role', 'button');
    }

    // Run initialization
    initializeBamero();
});
