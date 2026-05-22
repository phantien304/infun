<footer class="main">
    @yield('footer_top')
    <section class="section-padding footer-mid">
        <div class="container-xl pt-15 pb-20">
            <div class="row">
                <div class="col-xl-12">
                    <div class="content-top-footer">
                        <div class="row">
                            <div class="col">
                                <div class="content-company-infunstudio list-menu-ft">
                                    <div class="content-company-infunstudio">
                                        <div class="logo">
                                            <a class="mb-4 d-block img-cover image-ft text-center" href="/">
                                                <img src="{!! getConfigDb('config_logo') !!}" alt="{!! getConfigDb('seo_title_home') !!}">
                                            </a>
                                        </div>
                                        {!! getConfigDb('config_footer1') !!}
                                    </div>
                                </div>
                            </div>
                            {{-- <div class="footer-link-widget col"> --}}
                            {{-- --}}
                            {{-- </div> --}}
                            <div class="footer-link-widget col">
                                <div class="block-menu-footer list-menu-ft mb-20">
                                    {!! getConfigDb('config_footer2') !!}
                                </div>
                                <div class="block-menu-footer list-menu-ft">
                                    {!! getConfigDb('config_footer3') !!}
                                </div>
                            </div>
                            <div class="col">
                                <div class="block-menu-footer list-menu-ft">
                                    {!! getConfigDb('config_footer4') !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="container-xl pb-30 wow fadeIn animated">
        <div class="row align-items-center">
            <div class="col-12 mb-30">
                <div class="footer-bottom"></div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6">
                <p class="font-sm mb-0">© 2021, <strong class="text-body">DAgencyVn.net</strong><br>All rights reserved
                </p>
            </div>
            <div class="col-xl-4 col-lg-6 text-center d-none d-xl-block">
                <div class="hotline d-lg-inline-flex mr-30">
                    <img src="/web/images/theme/icons/phone-call.svg" alt="hotline">
                    <p>{!! getConfigDb('config_telephone') !!}<span>{!! getConfigDb('config_opening_time') !!}</span>
                    </p>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 text-end d-none d-md-block">
                <div class="mobile-social-icon">
                    <h6>Theo dõi chúng tôi trên</h6>
                    <a href="{!! getConfigDb('config_facebook') !!}" title="facebook" target="_blank">
                        <img src="/web/images/theme/icons/icon-facebook-white.svg" alt="facebook">
                    </a>
                    <a href="{!! getConfigDb('config_instagram') !!}" title="instagram" target="_blank">
                        <img src="/web/images/theme/icons/icon-instagram-white.svg" alt="instagram">
                    </a>
                    <a href="{!! getConfigDb('config_tiktok') !!}" title="tiktok" target="_blank">
                        <img src="/web/images/theme/icons/icons8-tiktok.svg" alt="tiktok">
                    </a>
                    <a href="{!! getConfigDb('config_threads') !!}" title="threads" target="_blank">
                        <img src="/web/images/theme/icons/icons8-threads.svg" alt="threads">
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- Preloader Start -->
{{-- <div id="preloader-active"> --}}
{{-- <div class="preloader d-flex align-items-center justify-content-center"> --}}
{{-- <div class="preloader-inner position-relative"> --}}
{{-- <div class="text-center"> --}}
{{-- <img src="/client/images/theme/loading.gif" alt=""> --}}
{{-- </div> --}}
{{-- </div> --}}
{{-- </div> --}}
{{-- </div> --}}
<!-- Vendor JS-->
<div id="link-cart" style="display: none;"></div>
<div id="dialog-confirm"></div>
<div id="dialog-consult-sign"></div>
<div id="dialog-confirm-wishlist" style="text-align: center;"></div>
<div id="dialog-confirm-wishlist-nlg" style="text-align: center"></div>
<div id="fb-root"></div>
<script type="text/javascript">
    var urlAccountWishlist = '{{ routeArea('account.wishlist') }}';
    var urlAccountLogin = '{{ routeArea('auth.login') }}';
    (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s);
        js.id = id;
        js.async = true;
        js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.3&appId=156028201458706";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
</script>
<script type="text/javascript" src="{!! publicUrl('web/js/jquery-3.6.0.min.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/bootstrap.min.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/lib.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/style.js?v=' . getConfigDb('config_theme_version')) !!}"></script>
