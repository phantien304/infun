if (typeof checkoutShipping == 'undefined') {
    var checkoutShipping = '';
}

$(window).on("load", function () {
    $("#preloader-active").delay(0).fadeOut("slow");
    $("body").delay(0).css({
        overflow: "visible"
    });
    $("#onloadModal").modal("show");
});

$(document).ready(function () {
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        $('.list-menu .dropdown .wrap-menu').click(function () {
            let isActive = $(this).closest('.dropdown.active');
            let isOpen = $(this).closest('.dropdown.open');
            if (isActive.length && isOpen.length) {
                isActive.removeClass('active open');
            } else if (isActive.length) {
                isActive.addClass('open');
            } else {
                $('.list-menu .dropdown').removeClass('active open');
                $(this).closest('li').addClass('active open');
            }
        });
    }
    $('.select-level-1 .list-select-area li').click(function () {
        if (!$(this).hasClass('active')) {
            $('.select-level-1 .list-select-area li').removeClass('active');
            $(this).addClass('active');
        } else {
            $(this).removeClass('active');
        }
    });
    $(".wrap-thumb").on("mouseover", function () {
        $(this).siblings().removeClass("active").end().addClass("active");
        $(this).children().trigger("click");
        let img_zoom = $(this).find("img").attr("data-zoom-image");
        let img_big = $(this).find("img").attr("data-img-big");
        $('.sys_img_big').attr('data-zoom-image', img_zoom);
        $('.sys_img_big').attr('src', img_big);
    });
    let $slickSlider = $('.multiple-item');
    $('.multiple-item').slick({
        dots: true,
        infinite: true,
        autoplay: true,
        speed: 300,
        slidesToShow: 1,
        arrows: false
    });
    $('.slick-prev').click(function () {
        $slickSlider.slick("slickPrev");
    });
    $('.slick-next').click(function () {
        $slickSlider.slick("slickNext");
    });
    /*-----------------Menu Stick-----------------*/
    var header = $('.sticky-bar');
    var win = $(window);
    win.on('scroll', function () {
        var scroll = win.scrollTop();
        if (scroll < 200) {
            header.removeClass('stick');
            $('.header-style-2 .categories-dropdown-active-large').removeClass('open');
            $('.header-style-2 .categories-button-active').removeClass('open');
        } else {
            header.addClass('stick');
        }
    });
    if ($("#slider-range").length) {
        $("#slider-range").slider({
            range: true,
            min: 10000,
            max: 2000000,
            values: [priceGteq, priceLteq],
            slide: function (event, ui) {
                $("#price_gteq").val(number_format(ui.values[0]) + 'đ');
                $("#price_lteq").val(number_format(ui.values[1]) + 'đ');
            }
        });
        $("#price_gteq").val(number_format($("#slider-range").slider("values", 0)) + 'đ');
        $("#price_lteq").val(number_format($("#slider-range").slider("values", 1)) + 'đ');
    }
    $('.nav-link').on('shown.bs.tab', function () {
        let tabPane = $($(this).attr('href'));
        $('.hero-slider-1', tabPane).slick('refresh');
    });
    $('.hero-slider-1').slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        fade: true,
        loop: true,
        dots: false,
        arrows: true,
        prevArrow: '<span class="slider-btn slider-prev"><i class="fi-rs-angle-left"></i></span>',
        nextArrow: '<span class="slider-btn slider-next"><i class="fi-rs-angle-right"></i></span>',
        appendArrows: '.hero-slider-1-arrow',
        autoplay: true,
    });
    $(".carausel-8-columns").each(function (key, item) {
        let id = $(this).attr("id");
        let sliderID = '#' + id;
        let appendArrowsClassName = '#' + id + '-arrows';
        $(sliderID).slick({
            dots: false,
            infinite: true,
            speed: 300,
            arrows: true,
            autoplay: false,
            slidesToShow: 8,
            slidesToScroll: 4,
            loop: true,
            adaptiveHeight: true,
            responsive: [
                {
                    breakpoint: 1025,
                    settings: {
                        slidesToShow: 4,
                        slidesToScroll: 4,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2
                    }
                }
            ],
            prevArrow: '<span class="slider-btn slider-prev"><i class="fi-rs-arrow-small-left"></i></span>',
            nextArrow: '<span class="slider-btn slider-next"><i class="fi-rs-arrow-small-right"></i></span>',
            appendArrows: (appendArrowsClassName),
        });
    });
    $(".carausel-10-columns").each(function (key, item) {
        var id = $(this).attr("id");
        var sliderID = "#" + id;
        var appendArrowsClassName = "#" + id + "-arrows";

        $(sliderID).slick({
            dots: false,
            infinite: true,
            speed: 300,
            arrows: true,
            autoplay: false,
            slidesToShow: 10,
            slidesToScroll: 1,
            loop: true,
            adaptiveHeight: true,
            responsive: [
                {
                    breakpoint: 1025,
                    settings: {
                        slidesToShow: 4,
                        slidesToScroll: 4
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2
                    }
                }
            ],
            prevArrow: '<span class="slider-btn slider-prev"><i class="fi-rs-arrow-small-left"></i></span>',
            nextArrow: '<span class="slider-btn slider-next"><i class="fi-rs-arrow-small-right"></i></span>',
            appendArrows: appendArrowsClassName
        });
    });
    $(".carausel-6-columns").each(function (key, item) {
        let id = $(this).attr("id");
        let sliderID = '#' + id;
        let appendArrowsClassName = '#' + id + '-arrows';
        $(sliderID).slick({
            dots: false,
            infinite: true,
            speed: 300,
            arrows: true,
            autoplay: false,
            slidesToShow: 5,
            slidesToScroll: 3,
            loop: true,
            adaptiveHeight: true,
            responsive: [
                {
                    breakpoint: 1025,
                    settings: {
                        slidesToShow: 4,
                        slidesToScroll: 4,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 2
                    }
                }
            ],
            prevArrow: '<span class="slider-btn slider-prev"><i class="fi-rs-arrow-small-left"></i></span>',
            nextArrow: '<span class="slider-btn slider-next"><i class="fi-rs-arrow-small-right"></i></span>',
            appendArrows: (appendArrowsClassName),
        });
    });
    $(".carausel-5-columns").each(function (key, item) {
        let id = $(this).attr("id");
        let sliderID = '#' + id;
        let appendArrowsClassName = '#' + id + '-arrows';
        $(sliderID).slick({
            dots: false,
            infinite: true,
            speed: 2000,
            arrows: true,
            autoplay: true,
            slidesToShow: 4,
            slidesToScroll: 4,
            loop: true,
            adaptiveHeight: true,
            responsive: [
                {
                    breakpoint: 1025,
                    settings: {
                        slidesToShow: 4,
                        slidesToScroll: 4,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 1,
                        slidesToScroll: 1
                    }
                }
            ],
            prevArrow: '<span class="slider-btn slider-prev"><i class="fi-rs-arrow-small-left"></i></span>',
            nextArrow: '<span class="slider-btn slider-next"><i class="fi-rs-arrow-small-right"></i></span>',
            appendArrows: (appendArrowsClassName),
        });
    });
    $(".carausel-4-columns").each(function (key, item) {
        let id = $(this).attr("id");
        let sliderID = '#' + id;
        let appendArrowsClassName = '#' + id + '-arrows';

        $(sliderID).slick({
            dots: false,
            infinite: true,
            speed: 300,
            arrows: true,
            autoplay: false,
            slidesToShow: 4,
            slidesToScroll: 4,
            loop: true,
            adaptiveHeight: true,
            responsive: [
                {
                    breakpoint: 1025,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 1,
                        slidesToScroll: 1
                    }
                }
            ],
            prevArrow: '<span class="slider-btn slider-prev"><i class="fi-rs-arrow-small-left"></i></span>',
            nextArrow: '<span class="slider-btn slider-next"><i class="fi-rs-arrow-small-right"></i></span>',
            appendArrows: (appendArrowsClassName),
        });
    });

    var searchToggle = $('.categories-button-active');
    searchToggle.on('click', function (e) {
        e.preventDefault();
        if ($(this).hasClass('open')) {
            $(this).removeClass('open');
            $(this).siblings('.categories-dropdown-active-large').removeClass('open');
        } else {
            $(this).addClass('open');
            $(this).siblings('.categories-dropdown-active-large').addClass('open');
        }
    });
    $('.select-active').select2();
    $('.select-option-product').select2({
        minimumResultsForSearch: Infinity,
        placeholder: "--Chọn--",
    });
    if ($('.sort-by-product-area').length) {
        var $body = $('body'),
            $cartWrap = $('.sort-by-product-area'),
            $cartContent = $cartWrap.find('.sort-by-dropdown');
        $cartWrap.on('click', '.sort-by-product-wrap', function (e) {
            e.preventDefault();
            let $this = $(this);
            if (!$this.parent().hasClass('show')) {
                $this.siblings('.sort-by-dropdown').addClass('show').parent().addClass('show');
            } else {
                $this.siblings('.sort-by-dropdown').removeClass('show').parent().removeClass('show');
            }
        });
        /*Close When Click Outside*/
        $body.on('click', function (e) {
            var $target = e.target;
            if (!$($target).is('.sort-by-product-area') && !$($target).parents().is('.sort-by-product-area') && $cartWrap.hasClass('show')) {
                $cartWrap.removeClass('show');
                $cartContent.removeClass('show');
            }
        });
    }
    if ($('.sticky-sidebar').length) {
        $('.sticky-sidebar').theiaStickySidebar();
    }
    var $offCanvasNav = $(".mobile-menu"),
        $offCanvasNavSubMenu = $offCanvasNav.find(".dropdown");
    $offCanvasNavSubMenu.parent().prepend('<span class="menu-expand"><i class="fi-rs-angle-small-down"></i></span>');
    $offCanvasNavSubMenu.slideUp();
    $offCanvasNav.on("click", "li a, li .menu-expand", function (e) {
        var $this = $(this);
        if ($this.parent().attr("class").match(/\b(menu-item-has-children|has-children|has-sub-menu)\b/)
            && ($this.attr("href") === "#" || $this.hasClass("menu-expand"))) {
            e.preventDefault();
            if ($this.siblings("ul:visible").length) {
                $this.parent("li").removeClass("active");
                $this.siblings("ul").slideUp();
            } else {
                $this.parent("li").addClass("active");
                $this.closest("li").siblings("li").removeClass("active").find("li").removeClass("active");
                $this.closest("li").siblings("li").find("ul:visible").slideUp();
                $this.siblings("ul").slideDown();
            }
        }
    });
    $('.more_slide_open').slideUp();
    $('.more_categories').on('click', function () {
        $(this).toggleClass('show');
        $('.more_slide_open').slideToggle();
    });

    $("#dialog-confirm").dialog({
        autoOpen: false,
        title: "Giỏ hàng!",
        resizable: false,
        draggable: false,
        width: "400px",
        height: "auto",
        modal: true,
        buttons: {
            "Tiếp tục mua hàng": function () {
                $(this).dialog("close");
            }, "Thanh toán": function () {
                var href = $('#link-cart a').attr('href');
                location.href = href;
            }
        }
    });
    $("#dialog-confirm-wishlist").dialog({
        autoOpen: false,
        title: "Sản phẩm ưu thích!",
        resizable: false,
        draggable: false,
        width: "400px",
        height: "auto",
        modal: true,
        buttons: {
            "Tiếp tục mua hàng": function () {
                $(this).dialog("close");
            }, "Vào danh sách": function () {
                location.href = urlAccountWishlist;
            }
        }
    });
    $("#dialog-consult-sign").dialog({
        autoOpen: false,
        title: "Tư vấn ngay!",
        resizable: false,
        draggable: false,
        width: "400px",
        height: "auto",
        modal: true,
        buttons: {
            "Tiếp tục mua hàng": function () {
                $(this).dialog("close");
            }
        }
    });
    $("#dialog-confirm-wishlist-nlg").dialog({
        autoOpen: false,
        title: "Đăng nhập tài khoản!",
        resizable: false,
        draggable: false,
        width: "400px",
        height: "auto",
        modal: true,
        buttons: {
            "Tiếp tục mua hàng": function () {
                $(this).dialog("close");
            }, "Đăng nhập": function () {
                location.href = urlAccountLogin;
            }
        }
    });

    $(document).on('click', '.ui-widget-overlay', function () {
        $("div:ui-dialog:visible").dialog("close");
    });
    (function () {
        if (typeof variantMatrix === 'undefined') { window.variantMatrix = []; }
        if (typeof defaultVariant === 'undefined') { window.defaultVariant = null; }

        window.dumpVariants = function () {
            console.table((variantMatrix || []).map(function (v) {
                return {
                    id: v.id, sku: v.sku, price: v.price,
                    available: v.available, subtract: v.subtract,
                    has_stock: v.has_stock,
                    attrs: JSON.stringify(v.attributes),
                };
            }));
            console.log('Tổng variant:', (variantMatrix || []).length,
                '; có stock row:', (variantMatrix || []).filter(function (v) { return v.has_stock; }).length);
        };

        var PRICE_SELECTOR = 'b#price-product';
        var __basePrice = (typeof priceProduct === 'number' ? priceProduct : 0);

        var VARIANT_OPTION_IDS = (function () {
            var ids = new Set();
            (Array.isArray(variantMatrix) ? variantMatrix : []).forEach(function (v) {
                Object.keys(v.attributes || {}).forEach(function (k) {
                    ids.add(parseInt(k, 10));
                });
            });
            return ids;
        })();

        function readSelectedAttributes() {
            var attrs = {};
            $('.input-option input[type=radio]:checked, .input-option input[type=checkbox]:checked')
                .each(function () {
                    var optionId = parseInt($(this).attr('data-option-id'), 10);
                    var optionValueId = parseInt($(this).attr('data-option-value-id'), 10);
                    if (!optionId || !optionValueId) return;
                    if (!VARIANT_OPTION_IDS.has(optionId)) return;
                    attrs[optionId] = optionValueId;
                });
            $('.input-option select').each(function () {
                var $opt = $(this).find(':selected');
                if (!$(this).val()) return;
                var optionId = parseInt($(this).attr('data-option-id'), 10);
                var optionValueId = parseInt($opt.attr('data-option-value-id'), 10);
                if (!optionId || !optionValueId) return;
                if (!VARIANT_OPTION_IDS.has(optionId)) return;
                attrs[optionId] = optionValueId;
            });
            return attrs;
        }

        function findVariant(selected) {
            var keys = Object.keys(selected);
            if (!keys.length || !Array.isArray(variantMatrix)) return null;

            return variantMatrix.find(function (v) {
                var vKeys = Object.keys(v.attributes || {});
                if (vKeys.length !== keys.length) return false;
                return keys.every(function (k) {
                    return String(v.attributes[k]) === String(selected[k]);
                });
            }) || null;
        }

        function formatPriceLabel(price) {
            if (!price || price <= 0) return 'Liên hệ';
            return number_format(price) + 'đ';
        }

        function customFieldSurcharge() {
            var total = 0;
            $('.input-option input[type=radio]:checked, .input-option input[type=checkbox]:checked').each(function () {
                total += parseFloat($(this).attr('data-price')) || 0;
            });
            $('.input-option select').each(function () {
                total += parseFloat($(this).find('option:selected').attr('data-price')) || 0;
            });
            $('.input-option input.input-choose, .input-option textarea.textarea-choose').each(function () {
                if (($(this).val() || '').trim() !== '') {
                    total += parseFloat($(this).attr('data-price')) || 0;
                }
            });
            return total;
        }

        function renderTotalPrice() {
            $(PRICE_SELECTOR).html(formatPriceLabel(__basePrice + customFieldSurcharge()));
        }

        function goToImageBySrc(targetSrc) {
            if (!targetSrc) return false;
            var $slider = $('.product-image-slider');
            if (!$slider.length || !$slider.hasClass('slick-initialized')) return false;

            var slick = $slider.slick('getSlick');
            var $slides = slick.$slides;
            for (var i = 0; i < $slides.length; i++) {
                if ($($slides[i]).find('img').attr('src') === targetSrc) {
                    $slider.slick('slickGoTo', i);
                    return true;
                }
            }
            return false;
        }

        function resetMainSlider() {
            if (galleryShowingVariant && renderGallery(window.productGallery)) {
                galleryShowingVariant = false;
                return;
            }
            var $slider = $('.product-image-slider');
            if ($slider.length && $slider.hasClass('slick-initialized')) {
                $slider.slick('slickGoTo', 0);
            }
        }

        function swapMainImage(src) {
            if (!src) return;
            if (goToImageBySrc(src)) return;
            $('.product-image-slider .slick-active img').attr('src', src);
            $('.zoomWindowContainer div').css('background-image', 'url(' + src + ')');
        }

        var galleryShowingVariant = false;
        function renderGallery(images) {
            var $main = $('.product-image-slider');
            var $thumbs = $('.slider-nav-thumbnails');
            if (!$main.hasClass('slick-initialized') || !Array.isArray(images) || !images.length) {
                return false;
            }
            $main.slick('slickRemove', null, null, true);
            $thumbs.slick('slickRemove', null, null, true);
            images.forEach(function (im) {
                $main.slick('slickAdd', '<figure class="border-radius-10"><img src="' + im.full + '" alt="' + (im.alt || '') + '"></figure>');
                $thumbs.slick('slickAdd', '<div><img src="' + im.thumb + '" alt="' + (im.alt || '') + '"></div>');
            });
            $main.slick('slickGoTo', 0, true);
            $('.slider-nav-thumbnails .slick-slide').removeClass('slick-active').eq(0).addClass('slick-active');
            return true;
        }

        function applyVariant(variant, swapImg) {
            if (!variant) return;
            if (typeof swapImg === 'undefined') swapImg = true;

            var currentPrice = (typeof variant.effective_price === 'number')
                ? variant.effective_price
                : variant.price;
            __basePrice = currentPrice;
            renderTotalPrice();
            updateDiscountBadge(variant);

            var inStock = ALL_OOS
                       || !variant.subtract
                       || (variant.available && variant.available > 0);
            if ($('#button-cart').length && $('#button-contact').length) {
                $('#button-cart').toggle(!!inStock);
                $('#button-contact').toggle(!inStock);
            }

            if (swapImg) {
                var vGallery = (window.variantGallery || {})[variant.id];
                if (vGallery && vGallery.length && renderGallery(vGallery)) {
                    galleryShowingVariant = true;
                } else {
                    if (galleryShowingVariant && renderGallery(window.productGallery)) {
                        galleryShowingVariant = false;
                    }
                    if (variant.image) swapMainImage(variant.image);
                }
            }
        }

        function updateDiscountBadge(variant) {
            var currentPrice, refPrice;

            if (typeof variant === 'number') {
                currentPrice = variant;
                refPrice = typeof productBasePrice === 'number' ? productBasePrice : 0;
            } else if (variant && typeof variant === 'object') {
                currentPrice = (typeof variant.effective_price === 'number')
                    ? variant.effective_price
                    : variant.price;
                if (typeof variant.strike_price === 'number') {
                    refPrice = variant.strike_price;
                } else if (variant.strike_price === null) {
                    refPrice = 0;
                } else {
                    refPrice = (variant.regular_price && variant.regular_price > 0)
                        ? variant.regular_price
                        : (typeof productBasePrice === 'number' ? productBasePrice : 0);
                }
            } else {
                currentPrice = 0;
                refPrice = 0;
            }

            if (!refPrice || !currentPrice || currentPrice >= refPrice) {
                $('#price-product-old').hide();
                $('#discount-badge').hide();
                return;
            }
            var percent = Math.round((refPrice - currentPrice) / refPrice * 100);
            $('#price-product-old').show().html(formatPriceLabel(refPrice));
            $('#discount-badge-value').text(percent);
            $('#discount-badge').show();
        }

        var ALL_OOS = Array.isArray(variantMatrix) && variantMatrix.length > 0 &&
            variantMatrix.every(function (v) {
                return v.subtract && !(v.available && v.available > 0);
            });
        if (ALL_OOS) {
            console.warn('[variant] Tất cả variant available=0 + subtract=true — '
                + 'nghi data drift, bỏ qua OOS check. Verify product_stock.on_hand.');
        }

        function isValueAvailable(selected, optionId, valueId) {
            if (ALL_OOS) return true;
            var hypo = Object.assign({}, selected);
            hypo[optionId] = valueId;
            var hypoKeys = Object.keys(hypo);

            return variantMatrix.some(function (v) {
                var attrs = v.attributes || {};
                var matches = hypoKeys.every(function (k) {
                    return String(attrs[k]) === String(hypo[k]);
                });
                if (!matches) return false;
                return !v.subtract || (v.available && v.available > 0);
            });
        }

        function isSelectionFeasible(selected) {
            var keys = Object.keys(selected);
            if (!keys.length) return true;
            return variantMatrix.some(function (v) {
                var attrs = v.attributes || {};
                var matches = keys.every(function (k) {
                    return String(attrs[k]) === String(selected[k]);
                });
                if (!matches) return false;
                return !v.subtract || (v.available && v.available > 0);
            });
        }

        function deselectOption(optionId) {
            var $group = $('#option-' + optionId);
            $group.find('input[type=radio], input[type=checkbox]')
                .prop('checked', false);
            $group.find('.active').removeClass('active');
            $group.removeClass('has-choose');
            if ($group.is('select')) {
                $group.val('');
            }
            $('#option-value-' + optionId).val('');
        }

        function refreshAvailability() {
            if (!Array.isArray(variantMatrix) || !variantMatrix.length) return;
            var selected = readSelectedAttributes();
            var hasSelection = Object.keys(selected).length > 0;

            $('.input-option input[type=radio][data-option-value-id], ' +
              '.input-option input[type=checkbox][data-option-value-id]').each(function () {
                var $input = $(this);
                var optionId = parseInt($input.attr('data-option-id'), 10);
                var valueId = parseInt($input.attr('data-option-value-id'), 10);
                if (!optionId || !valueId) return;
                if (!VARIANT_OPTION_IDS.has(optionId)) return;

                var isSelectedValue = String(selected[optionId]) === String(valueId);
                var ok = isValueAvailable(selected, optionId, valueId);
                var bad = hasSelection && !ok && !isSelectedValue;

                $input.prop('disabled', false);
                var $wrap = $input.parent();
                $wrap.toggleClass('out-of-stock', bad);
                $wrap.find('label').toggleClass('out-of-stock-label', bad);
            });

            $('.input-option select[data-option-id]').each(function () {
                var $select = $(this);
                var optionId = parseInt($select.attr('data-option-id'), 10);
                if (!optionId || !VARIANT_OPTION_IDS.has(optionId)) return;

                $select.find('option[data-option-value-id]').each(function () {
                    var $opt = $(this);
                    var valueId = parseInt($opt.attr('data-option-value-id'), 10);
                    if (!valueId) return;
                    var isSelectedValue = String(selected[optionId]) === String(valueId);
                    var ok = isValueAvailable(selected, optionId, valueId);
                    $opt.prop('disabled', hasSelection && !ok && !isSelectedValue);
                });
            });
        }

        function recompute(keepOptionId) {
            keepOptionId = parseInt(keepOptionId, 10);

            var selected = readSelectedAttributes();
            if (!isSelectionFeasible(selected)) {
                Object.keys(selected).forEach(function (optId) {
                    if (parseInt(optId, 10) === keepOptionId) return;
                    deselectOption(parseInt(optId, 10));
                });
            }

            var variant = findVariant(readSelectedAttributes());
            if (variant) {
                applyVariant(variant);
            } else if (defaultVariant) {
                applyVariant(defaultVariant, false);
            }
            refreshAvailability();
            renderTotalPrice();
        }

        $(function () {
            if (defaultVariant) applyVariant(defaultVariant, false);
            refreshAvailability();
        });

        $(document).on('click', '.radio-choose-v2 input', function () {
            var $input = $(this);
            var optionId = parseInt($input.data('option'), 10);
            var $wrapper = $input.parent();
            var $group = $input.closest('.radio-choose-v2');

            if ($wrapper.hasClass('active')) {
                $input.prop('checked', false);
                $wrapper.removeClass('active');
                $('#option-' + optionId).removeClass('has-choose');
                $('#option-value-' + optionId).val('');

                resetMainSlider();
                recompute(optionId);
                return;
            }

            $('#option-' + optionId).addClass('has-choose');
            $group.children('.active').removeClass('active');
            $wrapper.addClass('active');
            $('#option-value-' + optionId).val($input.next('label').text().trim());
            var swatch = $input.attr('data-image');
            if (swatch) swapMainImage(swatch);

            recompute(optionId);
        });

        $(document).on('click', '.checkbox-choose-v2 input', function () {
            var $input = $(this);
            var optionId = parseInt($input.data('option'), 10);
            var $group = $('#option-' + optionId);
            var hasChecked = $group.find('input:checked').length > 0;
            $group.toggleClass('has-choose', hasChecked);

            recompute(optionId);
        });

        $(document).on('change', '.select-choose-v2', function () {
            var $select = $(this);
            var optionId = parseInt($select.data('option'), 10);
            var $opt = $select.find(':selected');
            $('#option-' + optionId).toggleClass('has-choose', !!$select.val());
            $('#option-value-' + optionId).val($opt.attr('data-label') || '');
            recompute(optionId);
        });

        $(document).on('input change', '.input-choose, .textarea-choose', function () {
            var $el = $(this);
            $('#option-value-' + $el.data('option')).val($el.val());
            renderTotalPrice();
        });
    })();

    function clearOptionErrors() {
        $('.product-option .option-error').remove();
        $('#product-quantity').html('');
    }

    function renderOptionErrors(message) {
        if (!message || typeof message !== 'object') return;
        var $first = null;
        for (var key in message) {
            if (!Object.prototype.hasOwnProperty.call(message, key)) continue;
            var entry = message[key];
            if (key === 'quantity' && typeof entry === 'string') {
                var $q = $('<span class="error text-danger option-error">' + entry + '</span>');
                $('#product-quantity').html($q);
                if (!$first) $first = $q;
                continue;
            }
            var text = entry && entry.parent ? entry.parent : null;
            if (!text) continue;
            var $target = $('#option-' + key);
            if (!$target.length) continue;
            $target.siblings('.option-error').remove();
            var $err = $('<span class="error text-danger option-error d-block mt-1">' + text + '</span>');
            $target.after($err);
            if (!$first) $first = $err;
        }
        if ($first && $first.length && $first.offset()) {
            $('html, body').animate({ scrollTop: Math.max(0, $first.offset().top - 120) }, 200);
        }
    }

    function reshapeLaravelErrors(errors) {
        var out = {};
        if (!errors || typeof errors !== 'object') return out;
        for (var k in errors) {
            if (!Object.prototype.hasOwnProperty.call(errors, k)) continue;
            var msg = Array.isArray(errors[k]) ? errors[k][0] : errors[k];
            var m = k.match(/^option\.([^.]+)\.parent$/);
            if (m) { out[m[1]] = { parent: msg }; continue; }
            if (k === 'quantity') { out.quantity = msg; continue; }
        }
        return out;
    }

    function renderGlobalError(message) {
        if (!message || typeof message !== 'string') return;
        var $err = $('<span class="error text-danger option-error d-block mt-1"></span>').text(message);
        $('#product-quantity').html($err);
        if ($err.offset()) {
            $('html, body').animate({ scrollTop: Math.max(0, $err.offset().top - 120) }, 200);
        }
    }

    function handleCartAjaxError(xhr) {
        var json = (xhr && xhr.responseJSON) || {};
        if (xhr && xhr.status === 422 && json.errors) {
            var reshaped = reshapeLaravelErrors(json.errors);
            if (Object.keys(reshaped).length) {
                renderOptionErrors(reshaped);
                return;
            }
        }
        renderGlobalError(json.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
    }

    $(document).on('click change', '.product-option input, .product-option select, .product-option textarea', function () {
        var optionId = $(this).data('option');
        if (optionId) $('#option-' + optionId).siblings('.option-error').remove();
    });

    $(document).on('click', '#button-cart', function () {
        var addToCartUrl = $(this).data('url') || '/checkout/add-to-cart';
        $.ajax({
            url: addToCartUrl,
            type: 'post',
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content'),
            },
            data: $('.product-option input[type=\'text\'], .product-quantity input[type=\'number\'], .product-quantity input[type=\'hidden\'], .product-option input[type=\'number\'], .product-option input[type=\'hidden\'], .product-option input[type=\'radio\']:checked, .product-option input[type=\'checkbox\']:checked, .product-option select, .product-option textarea'),
            dataType: 'json',
            beforeSend: function () {
                $('#button-cart').attr("disabled", "disabled");
                clearOptionErrors();
            },
            success: function (json) {
                $('.success, .warning, .attention, .information').remove();
                var d = (json && json.data) || {};
                $('#dialog-confirm').dialog("open");
                $('#dialog-confirm').html(d.notice || (json && json.message) || '');
                if (d.cart) {
                    $('#link-cart').html('<a href="' + d.cart.url + '"/>');
                    $('#cart-total').html(d.cart.count);
                }
            },
            error: function (xhr) {
                if (xhr && xhr.status === 419) return;
                handleCartAjaxError(xhr);
            },
            complete: function () {
                $('#button-cart').removeAttr("disabled");
            },
        });
    });
    $(document).on('click', '#consult-sign', function () {
        var consultUrl = $(this).data('url') || '/checkout/consult-sign';
        $.ajax({
            url: consultUrl,
            type: 'post',
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content'),
            },
            data: $('.product-option input[type=\'text\'], .product-quantity input[type=\'number\'], .product-quantity input[type=\'hidden\'], .product-option input[type=\'number\'], .product-option input[type=\'hidden\'], .product-option input[type=\'radio\']:checked, .product-option input[type=\'checkbox\']:checked, .product-option select, .product-option textarea'),
            dataType: 'json',
            beforeSend: function () {
                $('#consult-sign').attr("disabled", "disabled");
                clearOptionErrors();
            },
            success: function (json) {
                $('.success, .warning, .attention, .information').remove();
                var d = (json && json.data) || {};
                $('#dialog-consult-sign').dialog("open");
                $('#dialog-consult-sign').html(d.notice || (json && json.message) || '');
            },
            error: function (xhr) {
                if (xhr && xhr.status === 419) return;
                handleCartAjaxError(xhr);
            },
            complete: function () {
                $('#consult-sign').removeAttr("disabled");
            },
        });
    });
    $(document).on('click', 'button[id^=\'button-upload\']', function () {
        let node = this;

        let optionId = $(node).attr('id').replace("button-upload", "");
        $('#form-upload').remove();
        $('body').prepend('<form enctype="multipart/form-data" id="form-upload" style="display: none;"><input type="file" name="file" /></form>');
        $('#form-upload input[name=\'file\']').trigger('click');

        if (typeof timer != 'undefined') {
            clearInterval(timer);
        }
        timer = setInterval(function () {
            if ($('#form-upload input[name=\'file\']').val() != '') {
                clearInterval(timer);

                $.ajax({
                    url: '/file/upload',
                    type: 'post',
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content'),
                    },
                    dataType: 'json',
                    data: new FormData($('#form-upload')[0]),
                    cache: false,
                    contentType: false,
                    processData: false,
                    beforeSend: function () {
                        $(node).attr('disabled', 'disabled').html("Đang tải lên...");
                        $('#input-option' + optionId).find('input[id="option-value-' + optionId + '"]').val('');
                    },
                    complete: function () {
                        $(node).removeAttr('disabled').html('<span class="fas fa-upload"></span> Upload File');
                    },
                    success: function (json) {
                        $(node).siblings('.text-danger, .text-grey-4').remove();
                        var d = (json && json.data) || {};
                        $('#input-option' + optionId).find('input[id="option-value-' + optionId + '"]').val(d.path || '');
                        $(node).after('<div class="text-grey-4">' + (d.name || '') + '</div>');
                    },
                    error: function (xhr) {
                        $(node).siblings('.text-danger, .text-grey-4').remove();
                        var json = (xhr && xhr.responseJSON) || {};
                        $(node).after('<div class="text-danger">' + (json.message || 'Tải tệp lên thất bại.') + '</div>');
                    }
                });
            }
        }, 500);
    });
    $(document).on('click', "#voucherForm button[type='submit']", function (e) {
        e.preventDefault();
        let voucherForm = $('#voucherForm');
        voucherForm.removeClass('has-error');
        $('.help-block').remove();
        let voucher = $('#voucherForm input[type="text"]').val();
        if (voucher) {
            $(".applyVoucher, #saveOrder").submit();
        } else {
            voucherForm.addClass('has-error');
            voucherForm.append('<div class="help-block">Vui lòng nhập mã quà tặng (Voucher)</div>')
        }
    });
    $(document).on('click', "#couponForm button[type='submit']", function (e) {
        e.preventDefault();
        let couponForm = $('#couponForm');
        couponForm.removeClass('has-error');
        $('.help-block').remove();
        let voucher = $('#couponForm input[type="text"]').val();
        if (voucher) {
            $(".applyCoupon, #saveOrder").submit();
        } else {
            couponForm.addClass('has-error');
            couponForm.append('<div class="help-block">Vui lòng nhập mã giảm giá (Coupon)</div>')
        }
    });
    $(document).on('click', ".form-subcriber button[type='submit']", function (e) {
        e.preventDefault();
        let emailElement = $(this).parent().find('input[type="email"]');
        let valid = validateEmail(emailElement.val());
        if (valid) {
            $(this).parent().parent().find('.noti-subcriber').html('<div class="help-block text-success">Đăng ký thành công</div>');
            emailElement.val('')
        } else {
            $(this).parent().parent().find('.noti-subcriber').html('<div class="help-block">Vui lòng nhập email</div>');
        }
    });
    $(document).on('click', '#confirm-delete', function (e) {
        e.preventDefault();
        $('#return_reason').find('.alert').remove();
        let selectedOption = $('select[name="return_reason"]').val();
        if (!selectedOption) {
            $('#return_reason').append('<div class="alert alert-danger mt-1" style="padding: 5px;"><small> Vui lòng chọn lý do</small></div>');
            return;
        }
        $('#confirmCancelOrder form').submit();
    });

    $(document).on('keyup', 'input.input-choose', function (event) {
        let optionId = $(this).data('option');
        $('#option-value-' + optionId).val($(this).val());
    });
    $(document).on('keyup', 'textarea.textarea-choose', function (event) {
        let optionId = $(this).data('option');
        $('#option-value-' + optionId).val($(this).val());
    });
    $(document).on('change', "#zoneShipping", function (e) {
        $.LoadingOverlay("show", {progress: true, size: 7});
        $.ajax({
            type: 'POST',
            headers: {
                'X-CSRF-Token': $("input[name='_token']").val()
            },
            url: '/resource/zone-shipping',
            data: {
                'zone_id': $(this).val(),
            },
            dataType: 'json',
        }).done(function (data) {
            $.LoadingOverlay("hide");
        });
    });
    $('input:radio[name="carrier_code"]').on('change', function () {
        let carrierCode = $("input[name='carrier_code']:checked").val();
        if (carrierCode) {
            getFeeShipping(carrierCode);
        }
    });

    let heightDescProduct = $(".product-info .inner .product-description").height();
    if (heightDescProduct < 500) {
        $(".product-info .inner .wrap-btn-more").css('display', 'none');
        $(".product-info .inner").addClass('expanded');
    } else {
        $(".product-info .inner .product-description").css('height', '500px');
    }
    $('.btn--view-more-desc').on('click', function (e) {
        e.preventDefault();
        this.expand = !this.expand;
        if ($('.product-info .inner').hasClass("expanded")) {
            $("html, body").animate({
                scrollTop: $(".tab-style3").offset().top
            }, 20);
        }
        $(this).closest('.product-info').find('.inner').toggleClass('expanded');
    });
});

var productDetails = function () {
    $('.product-image-slider').slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        infinite: false,
        arrows: false,
        fade: true,
        speed: 0,
    });

    $('.slider-nav-thumbnails').slick({
        slidesToShow: 4,
        slidesToScroll: 1,
        infinite: false,
        dots: false,
        speed: 0,
        prevArrow: '<button type="button" class="slick-prev"><i class="fi-rs-arrow-small-left"></i></button>',
        nextArrow: '<button type="button" class="slick-next"><i class="fi-rs-arrow-small-right"></i></button>'
    });

    $(document).on('click', '.slider-nav-thumbnails .slick-slide:not(.slick-cloned)', function () {
        var $thumb = $(this);
        var idx = parseInt($thumb.attr('data-slick-index'), 10);
        if (isNaN(idx) || idx < 0) return;

        var $strip = $('.slider-nav-thumbnails');
        $strip.removeClass('is-hovering');
        $strip.find('.slick-slide.is-hover-active').removeClass('is-hover-active');

        $strip.find('.slick-slide').removeClass('slick-current slick-active');
        $thumb.addClass('slick-current slick-active');

        var $mainSlider = $('.product-image-slider');
        if ($mainSlider.hasClass('slick-initialized')) {
            $mainSlider.slick('slickGoTo', idx);
        }
    });

    $(document).on('mouseenter', '.slider-nav-thumbnails .slick-slide:not(.slick-cloned)', function () {
        var $thumb = $(this);
        var $strip = $('.slider-nav-thumbnails');
        var $thumbImg = $thumb.find('img');
        if (!$thumbImg.length) return;

        $strip.addClass('is-hovering')
              .find('.slick-slide.is-hover-active')
              .removeClass('is-hover-active');
        $thumb.addClass('is-hover-active');

        var mainSrc = $thumbImg.attr('src').replace(/\/\d+x\d+\//, '/1000x1000/');
        var $mainImg = $('.product-image-slider .slick-active img');
        if (!$mainImg.length) return;

        $mainImg.attr('src', mainSrc);
        $('.zoomWindowContainer div').css('background-image', 'url(' + mainSrc + ')');
    });

    $(document).on('click', '.slider-nav-thumbnails .slick-slide', function () {
        var $strip = $('.slider-nav-thumbnails');
        $strip.removeClass('is-hovering');
        $strip.find('.slick-slide.is-hover-active').removeClass('is-hover-active');
    });

    $('.slider-nav-thumbnails .slick-slide').removeClass('slick-active');
    $('.slider-nav-thumbnails .slick-slide').eq(0).addClass('slick-active');
    $('.product-image-slider').on('beforeChange', function (event, slick, currentSlide, nextSlide) {
        var mySlideNumber = nextSlide;
        $('.slider-nav-thumbnails .slick-slide').removeClass('slick-active');
        $('.slider-nav-thumbnails .slick-slide').eq(mySlideNumber).addClass('slick-active');
    });
    $('.product-image-slider').on('beforeChange', function (event, slick, currentSlide, nextSlide) {
        var img = $(slick.$slides[nextSlide]).find("img");
        $('.zoomWindowContainer,.zoomContainer').remove();
        $(img).elevateZoom({
            zoomType: "inner",
            cursor: "crosshair",
            zoomWindowFadeIn: 500,
            zoomWindowFadeOut: 750
        });
    });
    if ($(".product-image-slider").length) {
        $('.product-image-slider .slick-active img').elevateZoom({
            zoomType: "inner",
            cursor: "crosshair",
            zoomWindowFadeIn: 500,
            zoomWindowFadeOut: 750
        });
    }
    $('.list-filter').each(function () {
        $(this).find('a').on('click', function (event) {
            event.preventDefault();
            $(this).parent().siblings().removeClass('active');
            $(this).parent().toggleClass('active');
            $(this).parents('.attr-detail').find('.current-size').text($(this).text());
            $(this).parents('.attr-detail').find('.current-color').text($(this).attr('data-color'));
        });
    });
};

function mobileHeaderActive() {
    var navbarTrigger = $(".burger-icon"),
        endTrigger = $(".mobile-menu-close"),
        container = $(".mobile-header-active"),
        wrapper4 = $("body");

    wrapper4.prepend('<div class="body-overlay-1"></div>');

    navbarTrigger.on("click", function (e) {
        e.preventDefault();
        container.addClass("sidebar-visible");
        wrapper4.addClass("mobile-menu-active");
    });

    endTrigger.on("click", function () {
        container.removeClass("sidebar-visible");
        wrapper4.removeClass("mobile-menu-active");
    });

    $(".body-overlay-1").on("click", function () {
        container.removeClass("sidebar-visible");
        wrapper4.removeClass("mobile-menu-active");
    });
}

productDetails();

mobileHeaderActive();

function countDownTime(dateEnd, productId) {
    let today = new Date();
    let bigDay = new Date('"' + dateEnd + '"');
    let timeLeft = (bigDay.getTime() - today.getTime());
    let e_daysLeft = timeLeft / 86400000;
    let daysLeft = Math.floor(e_daysLeft);

    let e_hrsLeft = (e_daysLeft - daysLeft) * 24;
    let hrsLeft = Math.floor(e_hrsLeft);
    if (hrsLeft < 10) {
        hrsLeft = '0' + hrsLeft;
    }

    let e_minsLeft = (e_hrsLeft - hrsLeft) * 60;
    let minsLeft = Math.floor(e_minsLeft);
    if (minsLeft < 10) {
        minsLeft = '0' + minsLeft;
    }
    let seksLeft = Math.floor((e_minsLeft - minsLeft) * 60);
    if (seksLeft < 10) {
        seksLeft = '0' + seksLeft;
    }
    if (bigDay.getTime() > today.getTime()) {
        document.getElementById("countdown_" + productId).innerHTML = 'Còn lại ' + daysLeft + ' ngày ' + hrsLeft + ':' + minsLeft + ':' + seksLeft;
    }
}

function getFeeShipping(carrierCode) {
    $.LoadingOverlay("show", {progress: true, size: 7});
    $.ajax({
        url: checkoutShipping + '?carrier_code=' + carrierCode,
        type: 'get',
        success: function (json) {
            if (json['success'] === true) {
                let data = json['data'];
                let shipping = data.filter(k => k.code == carrierCode)[0];
                if (shipping) {
                    $('#' + carrierCode + ' span.price').html(shipping.text);
                }
                let totalData = '';
                for (let i = 0; i < data.length; i++) {
                    if (i === (data.length - 1)) {
                        totalData += '<tr><td scope="col" colspan="2"><div class="divider-2 mt-10 mb-10"></div></td></tr>';
                    }
                    totalData += '<tr>' +
                        '<td class="cart_total_label"><h6 class="text-muted">' + data[i]['title'] + '</h6></td>' +
                        '<td class="cart_total_amount"><h5 class="text-brand text-end">' + data[i]['text'] + '</h5></td>' +
                        '</tr>';
                }
                $('#total-data').html(totalData);
            }
            $.LoadingOverlay("hide");
        },
    });
}

if (typeof carrierCode !== 'undefined') {
    getFeeShipping(carrierCode);
}

function userWishlist($productId) {
    $('#wishlist').removeClass('active');
    $.ajax({
        url: urlUserWishlist + '?product_id=' + $productId,
        type: 'get',
        dataType: 'json',
        success: function (json) {
            var d = (json && json.data) || {};
            $('#dialog-confirm-wishlist').dialog('open');
            $('#dialog-confirm-wishlist').html('<b class="pt-1 pb-1">' + ((json && json.message) || '') + '</b>');
            if (!d.deleted) {
                $('#wishlist').addClass('active');
            }
            $('#count-wishlist').html(d.total);
        },
        error: function (xhr) {
            if (xhr && (xhr.status === 401 || xhr.status === 403)) {
                $('#dialog-confirm-wishlist-nlg').dialog('open');
                $('#dialog-confirm-wishlist-nlg').html('<b class="pt-1 pb-1">Bạn cần đăng nhập tài khoản để thêm sản phẩm yêu thích</b>');
            } else {
                var json = (xhr && xhr.responseJSON) || {};
                $('#dialog-confirm-wishlist').dialog('open');
                $('#dialog-confirm-wishlist').html('<b class="pt-1 pb-1">' + (json.message || 'Có lỗi xảy ra.') + '</b>');
            }
        }
    });
}

window.onscroll = function () {
    scrollFunction()
};

function isNaN(x) {
    x = Number(x);
    return x != x;
}

function scrollFunction() {
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        $('#toppage-btn').fadeIn(200);
    } else {
        $('#toppage-btn').fadeOut(200);
    }
}

function topFunction() {
    $('body,html').animate({
        scrollTop: 0
    }, 500);
}

function number_format(number, decimals, dec_point, thousands_point) {

    if (number == null || !isFinite(number)) {
    }

    if (!decimals) {
        let len = number.toString().split('.').length;
        decimals = len > 1 ? len : 0;
    }

    if (!dec_point) {
        dec_point = '.';
    }

    if (!thousands_point) {
        thousands_point = ',';
    }

    number = parseFloat(number).toFixed(decimals);

    number = number.replace(".", dec_point);

    let splitNum = number.split(dec_point);
    splitNum[0] = splitNum[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands_point);
    number = splitNum.join(dec_point);

    return number;
}

function callResourceDistrict(zoneId) {
    let input = '<option value="">--Chọn--</option>';
    $('#inputDistrict').html(input);
    $('#inputWard').html(input);
    if (zoneId) {
        $.ajax({
            url: '/resource/district?zone_id=' + zoneId,
            type: 'get',
            success: function (json) {
                if (json['success'] === true) {
                    let data = json['data'];
                    for (let i = 0; i < data.length; i++) {
                        let selected = '';
                        if (data[i]['id'] == oldDistrictId) {
                            selected = 'selected';
                        }
                        input += '<option value="' + data[i]['id'] + '"' + selected + '>' + data[i]['name'] + '</option>';
                    }
                    $('#inputDistrict').html(input);
                }
            },
        });
    }
}

function callResourceWard(wardId) {
    let input = '<option value="">--Chọn--</option>';
    $('#inputWard').html(input);
    if (wardId) {
        $.ajax({
            url: '/resource/ward?district_id=' + wardId,
            type: 'get',
            success: function (json) {
                if (json['success'] === true) {
                    let data = json['data'];
                    for (let i = 0; i < data.length; i++) {
                        let selected = '';
                        if (data[i]['id'] == oldWardId) {
                            selected = 'selected';
                        }
                        input += '<option value="' + data[i]['id'] + '"' + selected + '>' + data[i]['name'] + '</option>';
                    }
                    $('#inputWard').html(input);
                }
            },
        });
    }
}

function passwordShowHide() {
    let x = document.getElementById("password");
    let show_eye = document.getElementById("show_eye");
    let hide_eye = document.getElementById("hide_eye");
    hide_eye.classList.remove("d-none");
    if (x.type === "password") {
        x.type = "text";
        show_eye.style.display = "none";
        hide_eye.style.display = "block";
    } else {
        x.type = "password";
        show_eye.style.display = "block";
        hide_eye.style.display = "none";
    }
}

function validateEmail(email) {
    let atposition = email.indexOf("@");
    let dotposition = email.lastIndexOf(".");
    if (atposition < 1 || dotposition < (atposition + 2)
        || (dotposition + 2) >= email.length) {
        return false;
    }
    return true;
}
