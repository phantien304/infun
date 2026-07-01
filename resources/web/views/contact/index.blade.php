@php
    $imageDefault = thumbnail(getModuleConfig('img_default'), 800, 354);
    $urlContact = route('contact.index');
@endphp
@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! $urlContact !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $imageDefault !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! $urlContact !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $imageDefault !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! $titleSeo !!}","alternateName":"{!! $descriptionSeo !!}","url":"{!! $urlContact !!}"}
    </script>
@stop
@section('style')
    <style type="text/css">
        .has-error .help-block {
            color: red;
            margin: 3px 0 5px 0;
            font-size: 14px;
        }
    </style>
@stop
@section('script')
    <script type="text/javascript"
            src="https://maps.googleapis.com/maps/api/js?libraries=places&key=AIzaSyDTBYZd0Yjn8D7T5y70FLXNFt4WDKcSDSI&language=en"></script>
    <script type="text/javascript" src="{!! asset('web/js/gmap/gmap3.min.js') !!}"></script>
    <script type="text/javascript" src="{!! asset('web/js/gmap/gmap3.infobox.js') !!}"></script>
    <script type="text/javascript">
        var mapDiv, map, infobox;
        var lat = 21.014950951644074;
        var lon = 105.82410676261858;
        jQuery(document).ready(function ($) {
            mapDiv = $("#contact-map");
            mapDiv.height(360).gmap3({
                map: {
                    options: {
                        center: [lat, lon],
                        zoom: 15
                    }
                },
                marker: {
                    values: [
                        {latLng: [lat, lon], data: "65 P. Trần Quang Diệu, Chợ Dừa, Đống Đa, Hà Nội, Vietnam"},
                    ],
                    options: {
                        draggable: false
                    },
                    events: {
                        mouseover: function (marker, event, context) {
                            var map = $(this).gmap3("get"),
                                infowindow = $(this).gmap3({get: {name: "infowindow"}});
                            if (infowindow) {
                                infowindow.open(map, marker);
                                infowindow.setContent(context.data);
                            } else {
                                $(this).gmap3({
                                    infowindow: {
                                        anchor: marker,
                                        options: {content: context.data}
                                    }
                                });
                            }
                        },
                        mouseout: function () {
                            var infowindow = $(this).gmap3({get: {name: "infowindow"}});
                            if (infowindow) {
                                infowindow.close();
                            }
                        }
                    }
                }
            });
            $('#contact-form').submit(function (event) {
                event.preventDefault();
                let submit = $('form#contact-form button[type=submit]');
                submit.attr("disabled", true);
                submit.after('<span class="wait">&nbsp;<img src="{{ asset('web/images/theme/loading.gif') }}" style="width: 30px; margin-left:20px;" alt=""/></span>');
                $('.item-form-support').removeClass('has-error');
                $('.help-block').remove();
                var formData = {
                    '_token': $('input[name=_token]').val(),
                    'name': $('form#contact-form input[name=name]').val(),
                    'email': $('form#contact-form input[name=email]').val(),
                    'phone': $('form#contact-form input[name=phone]').val(),
                    'service': $('form#contact-form select[name=service]').val(),
                    'content': $('form#contact-form textarea[name=content]').val()
                };
                $.ajax({
                    type: 'POST',
                    url: $('#contact-form').attr('action'),
                    data: formData,
                    dataType: 'json',
                    encode: true
                }).done(function (data) {
                    // 200 -> { success:true, message }
                    $('form#contact-form').find("input, textarea, select").val('');
                    $('form#contact-form').append('<div class="alert alert-success mt-30">' + ((data && data.message) || '{{ trans('messages.contact.send_success') }}') + '</div>');
                    submit.attr("disabled", false);
                    $('.wait').remove();
                }).fail(function (xhr) {
                    // 422 -> { success:false, message, errors:{field:[...]} }; 500 -> { message }
                    var data = (xhr && xhr.responseJSON) || {};
                    var errors = data.errors || {};
                    var map = {
                        name: '#name-group',
                        email: '#email-group',
                        phone: '#phone-group',
                        service: '#request-service-group',
                        content: '#content-group'
                    };
                    var hadFieldError = false;
                    Object.keys(map).forEach(function (field) {
                        if (errors[field]) {
                            hadFieldError = true;
                            $(map[field]).addClass('has-error');
                            $(map[field] + ' .td-input').append('<div class="help-block">' + errors[field][0] + '</div>');
                        }
                    });
                    if (!hadFieldError) {
                        $('form#contact-form').append('<div class="alert alert-danger mt-30">' + (data.message || '{{ trans('messages.contact.error') }}') + '</div>');
                    }
                    submit.attr("disabled", false);
                    $('.wait').remove();
                });
            });
        });
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb', ['titlePage' => 'Liên hệ'])
    <div class="page-content pt-50">
        <div class="container-xl">
            <div class="row">
                <div class="col-lg-12 m-auto">
                    <section class="row align-items-end mb-50">
                        <h4 class="mb-20 text-brand">Chúng tôi đã sẵn sàng trả lời câu hỏi của bạn</h4>
                        <h1 class="display-6 mb-30 fw-600">{!! getConfigDb('config_name') !!} luôn mong muốn có được mối quan hệ cùng có
                            lợi lâu dài với khách hàng trong tương lai.</h1>
                    </section>
                </div>
            </div>
        </div>
        <section class="container-fluid mb-50">
            <div class="border-radius-15 overflow-hidden">
                <div class="approved" id="contact-map"></div>
            </div>
        </section>
        <div class="container-xl">
            <div class="row">
                <div class="col-lg-12 m-auto">
                    <section class="mb-50">
                        <div class="row">
                            <div class="col-lg-4 pr-50 mb-30">
                                <p>Điện thoại: {{ getConfigDb('config_telephone') }}</p>
                                <p>Facebook:
                                    <a href="{!! getConfigDb('config_facebook') !!}" target="_blank">
                                        In&Fun Studio
                                    </a>
                                </p>
                                <p>{!! getConfigDb('config_address') !!}</p>
                            </div>
                            <div class="col-xl-8 mb-30">
                                <div class="contact-from-area padding-20-row-col wow FadeInUp animated">
                                    <form action="{!! route('contact.send') !!}" method="post"
                                          class="contact-form-style" id="contact-form">
                                        @csrf
                                        <div class="row">
                                            <div class="col-lg-6 col-md-6" id="name-group">
                                                <div class="input-style mb-20 td-input">
                                                    <input type="text" name="name" class="form-control"
                                                           value="{{ auth()->check() ? auth()->user()->full_name : '' }}"
                                                           placeholder="Họ tên" required/>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 col-md-6" id="email-group">
                                                <div class="input-style mb-20 td-input">
                                                    <input type="email" name="email" class="form-control"
                                                           value="{{ auth()->check() ? auth()->user()->email : '' }}"
                                                           placeholder="Email" required/>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 col-md-6" id="phone-group">
                                                <div class="input-style mb-20 td-input">
                                                    <input type="tel" name="phone" value="" class="form-control"
                                                           placeholder="Số điện thoại" required/>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 col-md-6" id="request-service-group">
                                                <div class="mb-20 td-input">
                                                    <select class="form-control" name="service" required>
                                                        <option value="">--Chọn dịch vụ--</option>
                                                        <option value="Dịch vụ khắc dấu"> Dịch vụ khắc dấu</option>
                                                        <option value="Dịch vụ in ấn"> Dịch vụ in ấn</option>
                                                        <option value="Dịch vụ thiết kế"> Dịch vụ thiết kế</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-12 col-md-12" id="content-group">
                                                <div class="textarea-style mb-30 td-input">
                                                    <textarea name="content"
                                                              placeholder="Chúng tôi có thể giúp gì cho bạn?"></textarea>
                                                </div>
                                                <button class="submit submit-auto-width" type="submit">Gửi đi</button>
                                            </div>
                                        </div>
                                    </form>
                                    <p class="form-messege"></p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
@stop
