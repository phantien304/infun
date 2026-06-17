{{--
    Footer — đã convert sang Tailwind utility.
    Class custom theme (`.list-menu-ft`, `.mobile-social-icon`, `.hotline`) giữ
    nguyên vì rule định nghĩa trong main.css/custom.css (theme infunstudio).
    Chỉ utility wrapper (`row/col-*/mb-*/d-flex/text-*`) chuyển sang Tailwind.
--}}
<footer class="main">
    @yield('footer_top')
    <section class="footer-mid py-12">
        <div class="container mx-auto max-w-7xl px-4 pt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="content-company-infunstudio list-menu-ft">
                    <div class="content-company-infunstudio">
                        <div class="logo">
                            <a class="mb-4 block img-cover image-ft text-center" href="/">
                                <img src="{!! getConfigDb('config_logo') !!}" alt="{!! getConfigDb('seo_title_home') !!}">
                            </a>
                        </div>
                        {!! getConfigDb('config_footer1') !!}
                    </div>
                </div>
                <div class="footer-link-widget">
                    <div class="block-menu-footer list-menu-ft mb-5">
                        {!! getConfigDb('config_footer2') !!}
                    </div>
                    <div class="block-menu-footer list-menu-ft">
                        {!! getConfigDb('config_footer3') !!}
                    </div>
                </div>
                <div>
                    <div class="block-menu-footer list-menu-ft">
                        {!! getConfigDb('config_footer4') !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="container mx-auto max-w-7xl px-4 pb-8 wow fadeIn animated">
        <div class="border-t border-gray-200 pt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-center">
            <div class="md:col-span-1">
                <p class="text-xs mb-0">
                    © 2021, <strong class="text-body">DAgencyVn.net</strong><br>
                    All rights reserved
                </p>
            </div>
            <div class="hidden xl:flex justify-center">
                <div class="hotline inline-flex items-center gap-2">
                    <img src="/web/images/theme/icons/phone-call.svg" alt="hotline">
                    <p>{!! getConfigDb('config_telephone') !!}<span>{!! getConfigDb('config_opening_time') !!}</span></p>
                </div>
            </div>
            <div class="hidden md:block text-right">
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
<div id="link-cart" style="display: none;"></div>
<div id="dialog-confirm"></div>
<div id="dialog-consult-sign"></div>
<div id="dialog-confirm-wishlist" class="text-center"></div>
<div id="dialog-confirm-wishlist-nlg" class="text-center"></div>
<div id="fb-root"></div>
<script type="text/javascript">
    var urlAccountWishlist = '{{ route('account.wishlist') }}';
    var urlAccountLogin = '{{ route('auth.login') }}';
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
{{--
    Bootstrap JS giữ trong giai đoạn coexistence — page chưa convert vẫn cần
    Modal/Dropdown/Collapse cũ. Gỡ ở Phase 7 sau khi mọi page Bootstrap
    interactivity được thay bằng Alpine.
--}}
<script type="text/javascript" src="{!! publicUrl('web/js/bootstrap.min.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/lib.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/style.js?v=' . getConfigDb('config_theme_version')) !!}"></script>
