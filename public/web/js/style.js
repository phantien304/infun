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
    $(document).on('click', '#review ul.pagination a.page-link', function () {
        let review = $('#review');
        review.fadeOut('slow');
        review.load($(this).attr('data-action'));
        review.fadeIn('slow');
        $('html, body').animate({
            scrollTop: review.offset().top - 100
        }, 0);
        return false;
    });
    $(document).on('click', '.radio-choose input', function (event) {
        let optValues = $(this).val();
        let optionId = $(this).data('option');
        let type = $(this).data('type');
        let price = $(this).data('price');
        renderOptionHtmlV2(optionId, optValues, type);
        optionProductPrice(optionId, price, type);
        $(event.target).closest('#option-' + optionId).addClass('has-choose');
        $(event.target).closest('#option-' + optionId).children('.active').removeClass('active');
        $(event.target).parent().addClass('active');
        let img_zoom = $(this).attr('data-image');
        $('.product-image-slider .slick-active img').attr('src', img_zoom);
        $('.zoomWindowContainer div').css('background-image', 'url(' + img_zoom + ')');
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('change', '.select-choose', function (event) {
        let optValues = $(this).val();
        let optionId = $(this).find(":selected").data("option");
        let type = $(this).find(":selected").data("type");
        let price = $(this).find(":selected").data("price");
        renderOptionHtmlV2(optionId, optValues, type);
        optionProductPrice(optionId, price, type);
        $('#option-' + optionId).addClass('has-choose');
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('change', '.select-child-choose', function (event) {
        let optionId = $(this).find(":selected").data("option");
        let optionProduct = $('#option-choose-' + optionId);
        if (!$('#option-child-' + optionId).hasClass('has-choose')) {
            optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 1 : parseInt(optionProduct.val()) + 1);
        }
        $('#option-child-' + optionId).addClass('has-choose');
        if (optionProduct.val() == 2) {
            optionProduct.attr('data-price', $(this).find(":selected").data("price"));
        }
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('click', '.radio-child-choose input', function (event) {
        let optionId = $(this).data('option');
        let optionProduct = $('#option-choose-' + optionId);
        if (!$(event.target).closest('#option-child-' + optionId).hasClass('has-choose')) {
            optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 1 : parseInt(optionProduct.val()) + 1);
        }
        $(event.target).closest('#option-child-' + optionId).addClass('has-choose');
        $(event.target).closest('.radio-child-choose').children('.active').removeClass('active');
        $(event.target).parent().addClass('active');
        if (optionProduct.val() == 2) {
            optionProduct.attr('data-price', $(this).data('price'));
        }
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('click', '.image-child-choose input', function (event) {
        let optionId = $(this).data('option');
        let optionProduct = $('#option-choose-' + optionId);
        if (!$(event.target).closest('#option-child-' + optionId).hasClass('has-choose')) {
            optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 1 : parseInt(optionProduct.val()) + 1);
        }
        $(event.target).closest('#option-child-' + optionId).addClass('has-choose');
        $(event.target).closest('.image-child-choose').children('.active').removeClass('active');
        $(event.target).parent().addClass('active');
        if (optionProduct.val() == 2) {
            optionProduct.attr('data-price', $(this).data('price'));
        }
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('click', '.checkbox-child-choose input', function (event) {
        let optionId = $(this).data('option');
        let optionProduct = $('#option-choose-' + optionId);
        let checkboxChecked = $('#option-child-' + optionId + ' input:checked');
        if (checkboxChecked.length > 0 && !$(event.target).closest('#option-child-' + optionId).hasClass('has-choose')) {
            optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 1 : parseInt(optionProduct.val()) + 1);
            $(event.target).closest('#option-child-' + optionId).addClass('has-choose');
        }
        if (checkboxChecked.length === 0) {
            optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 0 : parseInt(optionProduct.val()) - 1);
            $(event.target).closest('#option-child-' + optionId).removeClass('has-choose');
            optionProduct.attr('data-price', 0);
        }
        if (optionProduct.val() == 2) {
            let checkedOptChild = 0;
            $.each(checkboxChecked, function () {
                checkedOptChild = checkedOptChild + (isNaN(parseInt($(this).data('price'))) ? 0 : parseInt($(this).data('price')));
            });
            optionProduct.attr('data-price', checkedOptChild);
        }
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('click', '.radio-choose-v2 input', function (event) {
        let optValues = $(this).val();
        let optionId = $(this).data('option');
        let optionProduct = $('#option-choose-' + optionId);
        if (!$(event.target).closest('#option-' + optionId).hasClass('has-choose')) {
            optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 2 : 0);
        }
        $(event.target).closest('#option-' + optionId).addClass('has-choose');
        $(event.target).closest('.radio-choose-v2').children('.active').removeClass('active');
        $(event.target).parent().addClass('active');

        let option = options.filter(opt => opt.id === parseInt(optionId))[0];
        option = option['product_option_values'];
        if (Array.isArray(option)) {
            let optionValues = option.filter(opt => opt.id === parseInt(optValues))[0];
            $("#option-value-" + optionId).val(optionValues['name']);
            let optionValues2 = optionValues['product_option_values2'];
            if (Array.isArray(optionValues2)) {
                $('#option-child-' + optionId).val(optionValues2[0]['id'])
            }
        }

        if (optionProduct.val() == 2) {
            optionProduct.attr('data-price', $(this).data('price'));
        }

        let img_zoom = $(this).attr('data-image');
        if (img_zoom) {
            $('.product-image-slider .slick-active img').attr('src', img_zoom);
            $('.zoomWindowContainer div').css('background-image', 'url(' + img_zoom + ')');
        }

        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('click', '.checkbox-choose-v2 input', function (event) {
        let optionId = $(this).data('option');
        let optionProduct = $('#option-choose-' + optionId);
        let checkboxChecked = $('#option-' + optionId + ' input:checked');
        if (checkboxChecked.length > 0 && !$(event.target).closest('#option-' + optionId).hasClass('has-choose')) {
            optionProduct.val(2);
            $(event.target).closest('#option-' + optionId).addClass('has-choose');
        }
        if (checkboxChecked.length === 0) {
            optionProduct.val(0);
            $(event.target).closest('#option-' + optionId).removeClass('has-choose');
            optionProduct.attr('data-price', 0);
        }

        let option = options.filter(opt => opt.id === parseInt(optionId))[0];
        option = option['product_option_values'];
        if (Array.isArray(option)) {
            $.each(checkboxChecked, function () {
                let optionValues = option.filter(opt => opt.id === parseInt($(this).val()))[0];
                let optionValues2 = optionValues['product_option_values2'];
                if (Array.isArray(optionValues2)) {
                    $('input[name="option[' + optionId + '][children][' + $(this).val() + '][]"]').val(optionValues2[0]['id'])
                }
            });
        }

        if (optionProduct.val() == 2) {
            let checkedOptChild = 0;
            $.each(checkboxChecked, function () {
                checkedOptChild = checkedOptChild + (isNaN(parseInt($(this).data('price'))) ? 0 : parseInt($(this).data('price')));
            });
            optionProduct.attr('data-price', checkedOptChild);
        }
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('change', '.select-choose-v2', function (event) {
        let optValues = $(this).val();
        let optionId = $(this).find(":selected").data("option");
        let optionProduct = $('input#option-choose-' + optionId);
        if (optValues !== '') {
            optionProduct.val(2);
            optionProduct.attr('data-price', $(this).find(":selected").data("price"));
            $('#option-value-' + optionId).val($(this).find(":selected").data("label"));

            let option = options.filter(opt => opt.id === parseInt(optionId))[0];
            option = option['product_option_values'];
            if (Array.isArray(option)) {
                let optionValues = option.filter(opt => opt.id === parseInt(optValues))[0];
                let optionValues2 = optionValues['product_option_values2'];
                if (Array.isArray(optionValues2)) {
                    $('#option-child-' + optionId).val(optionValues2[0]['id'])
                }
            }
        } else {
            optionProduct.val(0);
            optionProduct.attr('data-price', 0);
            $('#option-value-' + optionId).val('');
            $('#option-child-' + optionId).val('')
        }
        $('b#price-product').html(getPriceProduct());
    });
    $(document).on('click', '#button-cart', function () {
        $.ajax({
            url: '/checkout/add-to-cart',
            type: 'post',
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content'),
            },
            data: $('.product-option input[type=\'text\'], .product-quantity input[type=\'number\'], .product-quantity input[type=\'hidden\'], .product-option input[type=\'number\'], .product-option input[type=\'hidden\'], .product-option input[type=\'radio\']:checked, .product-option input[type=\'checkbox\']:checked, .product-option select, .product-option textarea'),
            dataType: 'json',
            beforeSend: function () {
                $('#button-cart').attr("disabled", "disabled");
                $('.product-info .col-xl-9 .text-danger').remove();
                $('#product-quantity').html('');
            },
            success: function (json) {
                $('.success, .warning, .attention, .information, .error').remove();
                if (json['success'] === false) {
                    for (i in json['message']) {
                        if (json['message'][i]['parent']) {
                            $('#option-' + i).after('<span class="error text-danger">' + json['message'][i]['parent'] + '</span>');
                        }
                        if (json['message'][i]['child']) {
                            let nameChild = options.filter(k => k.id == i)[0]['children']['name_display'] ?? '';
                            $('#option-child-' + i).after('<span class="error text-danger">' + json['message'][i]['child'] + nameChild + '</span>');
                        }
                    }
                    if (json['message']['quantity']) {
                        $('#product-quantity').html('<span class="error text-danger">' + json['message']['quantity'] + '</span>')
                    }
                }
                if (json['success'] === true) {
                    $('#dialog-confirm').dialog("open");
                    $('#dialog-confirm').html(json['data']['success_2']);
                    $('#link-cart').html('<a href="' + json['data']['link_cart'] + '"/>');
                    $('#cart-total').html(json['data']['total_cart_header']);
                }
                $('#button-cart').removeAttr("disabled");
            },
        });
    });
    $(document).on('click', '#consult-sign', function () {
        $.ajax({
            url: '/checkout/consult-sign',
            type: 'post',
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content'),
            },
            data: $('.product-option input[type=\'text\'], .product-quantity input[type=\'number\'], .product-quantity input[type=\'hidden\'], .product-option input[type=\'number\'], .product-option input[type=\'hidden\'], .product-option input[type=\'radio\']:checked, .product-option input[type=\'checkbox\']:checked, .product-option select, .product-option textarea'),
            dataType: 'json',
            beforeSend: function () {
                $('#consult-sign').attr("disabled", "disabled");
                $('.product-info .col-xl-9 .text-danger').remove();
                $('#product-quantity').html('');
            },
            success: function (json) {
                $('.success, .warning, .attention, .information, .error').remove();
                if (json['success'] === false) {
                    for (i in json['message']) {
                        if (json['message'][i]['parent']) {
                            $('#option-' + i).after('<span class="error text-danger">' + json['message'][i]['parent'] + '</span>');
                        }
                        if (json['message'][i]['child']) {
                            let nameChild = options.filter(k => k.id == i)[0]['children']['name_display'] ?? '';
                            $('#option-child-' + i).after('<span class="error text-danger">' + json['message'][i]['child'] + nameChild + '</span>');
                        }
                    }
                    if (json['message']['quantity']) {
                        $('#product-quantity').html('<span class="error text-danger">' + json['message']['quantity'] + '</span>')
                    }
                }
                if (json['success'] === true) {
                    $('#dialog-consult-sign').dialog("open");
                    $('#dialog-consult-sign').html(json['data']['success_2']);
                }
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
                        $('#input-option' + optionId + ' .col-xl-9 .text-danger').remove();
                        $('#input-option' + optionId + ' .col-xl-9 .text-grey-4').remove();
                        if (json['success'] === false) {
                            $(node).after('<div class="text-danger">' + json['message'] + '</div>');
                        }
                        if (json['success'] === true) {
                            $('#input-option' + optionId).find('input[id="option-value-' + optionId + '"]').val(json['data']['path']);
                            $(node).after('<div class="text-grey-4">' + json['data']['name'] + '</div>');
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                    }
                });
            }
        }, 500);
    });
    $(document).on('click', "#ratingForm button[type='submit']", function (e) {
        e.preventDefault();
        let ratingForm = $('#ratingForm');
        ratingForm.find('.alert').remove();
        $(this).prop('disabled', true);
        $.ajax({
            type: 'POST',
            headers: {
                'X-CSRF-Token': $("input[name='_token']").val()
            },
            url: ratingForm.attr('action'),
            data: {
                'author': $("input[name='author']").val(),
                'product_id': $("input[name='product_id']").val(),
                'email': $("input[name='email']").val(),
                'text': $("textarea[name='text']").val(),
                'rating': $('input:radio[name=rating]:checked').val(),
            },
            dataType: 'json',
        }).done(function (data) {
            $("button[type='submit']").removeAttr('disabled');
            if (!data.success) {
                for (i in data.message) {
                    $('#ratingForm').append('<div class="alert alert-danger">' + data.message[i] + '</div>');
                }
            } else {
                ratingForm.find("input").val('');
                ratingForm.find("textarea").val('');
                ratingForm.append('<div class="alert alert-success">' + data.message + '</div>');
                $('#review').load(urlListReview)
            }
        });
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
        loop: false,
        arrows: false,
        fade: false,
        asNavFor: '.slider-nav-thumbnails',
    });

    $('.slider-nav-thumbnails').slick({
        slidesToShow: 4,
        slidesToScroll: 2,
        asNavFor: '.product-image-slider',
        dots: false,
        focusOnSelect: true,
        prevArrow: '<button type="button" class="slick-prev"><i class="fi-rs-arrow-small-left"></i></button>',
        nextArrow: '<button type="button" class="slick-next"><i class="fi-rs-arrow-small-right"></i></button>'
    });

    /* Remove active class from all thumbnail slides */
    $('.slider-nav-thumbnails .slick-slide').removeClass('slick-active');

    /* Set active class to first thumbnail slides */
    $('.slider-nav-thumbnails .slick-slide').eq(0).addClass('slick-active');

    /* On before slide change match active thumbnail to current slide*/
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
    /*Elevate Zoom*/
    if ($(".product-image-slider").length) {
        $('.product-image-slider .slick-active img').elevateZoom({
            zoomType: "inner",
            cursor: "crosshair",
            zoomWindowFadeIn: 500,
            zoomWindowFadeOut: 750
        });
    }
    /*Filter color/Size*/
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

function renderOptionHtmlV2(optionId, optValues, type) {
    if (Array.isArray(options)) {
        let option = options.filter(opt => opt.id === parseInt(optionId))[0];
        option = option['product_option_values'];
        if (Array.isArray(option)) {
            let optionValues = option.filter(opt => opt.id === parseInt(optValues))[0];
            $("#option-value-" + optionId).val(optionValues['name']);
            let optionValues2 = optionValues['product_option_values2'];
            if (Array.isArray(optionValues2)) {
                if (type === 'radio') {
                    let input = '';
                    let valueChild = $("#option-child-" + optionId + " input[type=radio]:checked").data('value');
                    for (let i = 0; i < optionValues2.length; i++) {
                        let active = '', checked = '', disable = 'disabled';
                        if (valueChild == optionValues2[i]['value'] && parseInt(optionValues2[i]['quantity']) > 0) {
                            active = 'active';
                            checked = 'checked';
                        }
                        if (parseInt(optionValues2[i]['quantity']) > 0) {
                            disable = '';
                        }
                        input += '<div class="' + active + '">' +
                            '<input type="radio" name="option[' + optionId + '][children][]" ' + checked + ' ' +
                            disable + ' ' +
                            'id="opt-' + optionId + '-child-' + optionValues2[i]['id'] + '" ' +
                            'value="' + optionValues2[i]['id'] + '" ' +
                            'data-value="' + optionValues2[i]['value'] + '"' +
                            'data-option="' + optionId + '"' +
                            'data-quantity="' + optionValues2[i]['quantity'] + '"' +
                            'data-price="' + optionValues2[i]['price'] + '">' +
                            '<label for="opt-' + optionId + '-child-' + optionValues2[i]['id'] + '" class="' + disable + '">' +
                            optionValues2[i]['value'] + '</label>' +
                            '</div>';
                    }
                    $('#option-child-' + optionId).html(input);
                }
                if (type === 'image') {
                    let input = '';
                    let valueChild = $("#option-child-" + optionId + " input[type=radio]:checked").data('value');
                    for (let i = 0; i < optionValues2.length; i++) {
                        let active = '', checked = '', disable = 'disabled';
                        if (valueChild == optionValues2[i]['value'] && parseInt(optionValues2[i]['quantity']) > 0) {
                            active = 'active';
                            checked = 'checked';
                        }
                        if (parseInt(optionValues2[i]['quantity']) > 0) {
                            disable = '';
                        }
                        input += '<div class="' + active + '">' +
                            '<input type="radio" name="option[' + optionId + '][children][]"' + checked + ' ' +
                            disable + ' ' +
                            'id="opt-' + optionId + '-child-' + optionValues2[i]['id'] + '"' +
                            'value="' + optionValues2[i]['id'] + '"' +
                            'data-value="' + optionValues2[i]['value'] + '"' +
                            'data-option="' + optionId + '"' +
                            'data-quantity="' + optionValues2[i]['quantity'] + '"' +
                            'data-price="' + optionValues2[i]['price'] + '">' +
                            '<label for="opt-' + optionId + '-child-' + optionValues2[i]['id'] + '" class="' + disable + '">' +
                            '<img src="' + optionValues2[i]['image'] + '" alt="' + optionValues2[i]['value'] + '" class="img-thumbnail">' +
                            '</label>' +
                            '</div>';
                    }
                    $('#option-child-' + optionId).html(input);
                }
                if (type === 'select') {
                    let select = '<select name="option[' + optionId + '][children][]"' +
                        'class="select-child-choose select-option-product">' +
                        '<option value="" data-option="' + optionId + '">--Chọn--</option>';
                    let labelChild = $("#option-child-" + optionId + " select").find(":selected").data('label');
                    let valueChild = '';
                    for (let i = 0; i < optionValues2.length; i++) {
                        if (labelChild == optionValues2[i]['value']) {
                            valueChild = optionValues2[i]['id'];
                        }
                        select += '<option value="' + optionValues2[i]['id'] + '"' +
                            'data-label="' + optionValues2[i]['value'] + '"' +
                            'data-value="' + optionValues2[i]['id'] + '"' +
                            'data-option="' + optionId + '"' +
                            'data-quantity="' + optionValues2[i]['quantity'] + '"' +
                            'data-price="' + optionValues2[i]['price'] + '">' + optionValues2[i]['value'] + '</option>';
                    }
                    $('#option-child-' + optionId).html(select + '</select>');
                    selectJs = $('#option-child-' + optionId + ' .select-option-product').select2({
                        minimumResultsForSearch: Infinity,
                        placeholder: "--Chọn--"
                    });
                    if (valueChild) {
                        selectJs.val(valueChild).trigger("change");
                    }
                }
                if (type === 'checkbox') {
                    let input = '', checkedOptChild = [];
                    $.each($("#option-child-" + optionId + " input[type=checkbox]:checked"), function () {
                        checkedOptChild.push($(this).data('value'));
                    });
                    for (let i = 0; i < optionValues2.length; i++) {
                        let active = '', checked = '', disable = 'disabled';
                        if (checkedOptChild.includes(optionValues2[i]['value']) && parseInt(optionValues2[i]['quantity']) > 0) {
                            active = 'active';
                            checked = 'checked';
                        }
                        if (parseInt(optionValues2[i]['quantity']) > 0) {
                            disable = '';
                        }
                        input += '<div class="' + active + ' form-check form-check-inline">' +
                            '<input class="form-check-input" type="checkbox" ' +
                            disable + ' ' +
                            'id="opt-' + optionId + '-child-' + optionValues2[i]['id'] + '" ' +
                            'name="option[' + optionId + '][children][]" ' + checked + ' ' +
                            'value="' + optionValues2[i]['id'] + '" ' +
                            'data-value="' + optionValues2[i]['value'] + '"' +
                            'data-option="' + optionId + '"' +
                            'data-quantity="' + optionValues2[i]['quantity'] + '"' +
                            'data-price="' + optionValues2[i]['price'] + '">' +
                            '<label class="form-check-label" for="opt-' + optionId + '-child-' + optionValues2[i]['id'] + '" class="' + disable + '">' +
                            optionValues2[i]['value'] + '</label>' +
                            '</div>';
                    }
                    $('#option-child-' + optionId).html(input);
                }
            }
        }
    }
}

function optionProductPrice(optionId, price, type) {
    let optionProduct = $('#option-choose-' + optionId);
    if (!$('#option-' + optionId).hasClass('has-choose')) {
        optionProduct.val(isNaN(parseInt(optionProduct.val())) ? 1 : parseInt(optionProduct.val()) + 1);
    }
    if (optionProduct.val() == 2) {
        if (type === 'radio') {
            let priceOption = $('#option-child-' + optionId + ' input[name="option[' + optionId + '][children][]"]:checked').data('price');
            optionProduct.attr('data-price', priceOption);
        }
        if (type === 'image') {
            let priceOption = $('#option-child-' + optionId + ' input[name="option[' + optionId + '][children][]"]:checked').data('price');
            optionProduct.attr('data-price', priceOption);
        }
        if (type === 'select') {
            let priceOption = $('#option-child-' + optionId + ' select').find(":selected").data("price");
            optionProduct.attr('data-price', priceOption);
        }
        if (type === 'checkbox') {
            let checkedOptChild = 0;
            $.each($('#option-child-' + optionId + ' input:checked'), function () {
                checkedOptChild = checkedOptChild + (isNaN(parseInt(price)) ? 0 : parseInt(price));
            });
            optionProduct.attr('data-price', checkedOptChild);
        }
    }
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
            if (json['success']) {
                $('#dialog-confirm-wishlist').dialog('open');
                $('#dialog-confirm-wishlist').html('<b class="pt-1 pb-1">' + json['message'] + '</b>');
                if (!json['data']['delete']) {
                    $('#wishlist').addClass('active');
                }
                $('#count-wishlist').html(json['data']['total']);
            }
        }, error: function (xhr, ajaxOptions, thrownError) {
            $('#dialog-confirm-wishlist-nlg').dialog('open');
            $('#dialog-confirm-wishlist-nlg').html('<b class="pt-1 pb-1">Bạn cần đăng nhập tài khoản để thêm sản phẩm yêu thích</b>');
        }
    });
}

window.onscroll = function () {
    scrollFunction()
};

if (typeof urlListReview !== 'undefined') {
    $('#review').load(urlListReview);
}

function getPriceProduct() {
    let priceOption = 0;
    $('.product-info input[name="option_price"]').each(function () {
        if ($(this).val() == 2) {
            priceOption = priceOption + parseInt($(this).attr('data-price'));
        }
    });
    return number_format(priceProduct + priceOption) + 'đ';
}

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
