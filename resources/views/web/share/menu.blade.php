<header class="header-area header-style-1 header-height-2">
    <div class="header-middle header-middle-ptb-1 d-none d-lg-block">
        <div class="container-xl">
            <div class="header-wrap">
                <div class="logo logo-width-1">
                    <a href="{{ routeArea('home') }}" title="{!! getConfigDb('config_name') !!}">
                        <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{!! getConfigDb('config_name') !!}">
                    </a>
                </div>
                <div class="header-right">
                    <div class="search-style-2">
                        <form action="/san-pham" method="get">
                            <input type="text" name="product_description[name_cons]" class="form-control"
                                value="{{ data_get(request()->get('product_description'), 'name_cons') }}"
                                placeholder="Tìm sản phẩm...">
                        </form>
                    </div>
                    <div class="header-action-right">
                        <div class="header-action-2">
                            <div class="header-action-icon-2">
                                <a href="{!! routeArea('account.wishlist') !!}" title="Yêu thích">
                                    <img class="svgInject" alt="Sản phẩm yêu thích"
                                        src="/web/images/theme/icons/icon-heart.svg">
                                    <span class="pro-count blue" id="count-wishlist">
                                        @if (auth()->check())
                                            {{ session()->get(getCoreConfig('session.total_wishlist'), 0) }}
                                        @else
                                            0
                                        @endif
                                    </span>
                                </a>
                                <a href="{!! routeArea('account.wishlist') !!}" title="Yêu thích">
                                    <span class="lable">Yêu thích</span>
                                </a>
                            </div>
                            <div class="header-action-icon-2">
                                <a class="mini-cart-icon" href="{{ routeArea('checkout.cart') }}" title="Giỏ hàng">
                                    <img alt="Giỏ hàng" src="/web/images/theme/icons/icon-cart.svg">
                                    <span class="pro-count blue" id="cart-total">{!! session()->get('total_cart_header', 0) !!}</span>
                                </a>
                                <a class="mini-cart-icon" href="{{ routeArea('checkout.cart') }}" title="Giỏ hàng">
                                    <span class="lable">Giỏ hàng</span>
                                </a>
                            </div>
                            <div class="header-action-icon-2">
                                <a href="{!! routeArea('account.index') !!}" title="Tài khoản">
                                    <img class="svgInject" alt="Tài khoản" src="/web/images/theme/icons/icon-user.svg">
                                </a>
                                <a href="{!! routeArea('account.index') !!}" title="Tài khoản">
                                    <span class="lable ml-0">Tài khoản</span>
                                </a>
                                @if (auth()->check())
                                    <div class="cart-dropdown-wrap cart-dropdown-hm2 account-dropdown">
                                        <ul>
                                            <li>
                                                <a href="{{ routeArea('account.index') }}" title="Thông tin tài khoản">
                                                    <i class="fi fi-rs-user mr-10"></i>
                                                    Tài khoản của tôi</a>
                                            </li>
                                            <li>
                                                <a href="{{ routeArea('order.search') }}" title="Theo dõi đơn hàng">
                                                    <i class="fi fi-rs-location-alt mr-10"></i>
                                                    Theo dõi đơn hàng</a>
                                            </li>
                                            <li>
                                                <a href="{{ routeArea('account.wishlist') }}"
                                                    title="Sản phẩm yêu thích">
                                                    <i class="fi fi-rs-heart mr-10"></i>Sản phẩm yêu thích
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{ routeArea('account.logout') }}" title="Đăng xuất">
                                                    <i class="fi fi-rs-sign-out mr-10"></i>
                                                    Đăng xuất
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style type="text/css">
        @media only screen and (max-width: 479px) {
            .vertical-tab .nav-tabs {
                width: 100%;
                display: block;
                border: none;
            }

            .vertical-tab .nav-tabs li a {
                margin: 0 0 10px;
            }

            .vertical-tab .tab-content {
                padding: 25px 20px;
                display: block;
            }

            .vertical-tab .tab-content h3 {
                font-size: 18px;
            }
        }
    </style>
    <div class="header-bottom header-bottom-bg-color sticky-bar">
        <div class="container-xl">
            <div class="header-wrap header-space-between position-relative">
                <div class="logo logo-width-1 d-block d-lg-none">
                    <a href="{{ routeArea('home') }}" title="{!! getConfigDb('config_name') !!}">
                        <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{!! getConfigDb('config_name') !!}">
                    </a>
                </div>
                <div class="header-nav d-none d-lg-flex">
                    <div
                        class="main-menu main-menu-padding-1 main-menu-lh-2 d-none d-lg-block font-heading infunstudio">
                        @foreach ($menus as $item)
                            @php
                                echo $item['pc'];
                            @endphp
                        @endforeach
                    </div>
                </div>
                <div class="hotline d-none d-lg-flex">
                    <img src="/web/images/theme/icons/icon-headphone.svg" alt="hotline">
                    <p>{!! getConfigDb('config_telephone') !!}<span>Hỗ trợ 24/7</span></p>
                </div>
                <div class="header-action-icon-2 d-block d-lg-none">
                    <div class="burger-icon burger-icon-white">
                        <span class="burger-icon-top"></span>
                        <span class="burger-icon-mid"></span>
                        <span class="burger-icon-bottom"></span>
                    </div>
                </div>
                <div class="header-action-right d-block d-lg-none">
                    <div class="header-action-2">
                        <div class="header-action-icon-2">
                            <a href="{!! routeArea('account.wishlist') !!}">
                                <img alt="Sản phẩm yêu thích" src="/web/images/theme/icons/icon-heart.svg">
                                <span class="pro-count white" id="count-wishlist">
                                    @if (auth()->check())
                                        {{ session()->get(getCoreConfig('session.total_wishlist'), 0) }}
                                    @else
                                        0
                                    @endif
                                </span>
                            </a>
                        </div>
                        <div class="header-action-icon-2">
                            <a class="mini-cart-icon" href="{{ routeArea('checkout.cart') }}" title="Giỏ hàng">
                                <img alt="Giỏ hàng" src="/web/images/theme/icons/icon-cart.svg">
                                <span class="pro-count white" id="cart-total">{!! session()->get('total_cart_header', 0) !!}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="mobile-header-active mobile-header-wrapper-style">
    <div class="mobile-header-wrapper-inner">
        <div class="mobile-header-top">
            <div class="mobile-header-logo">
                <a href="{{ routeArea('home') }}" title="{!! getConfigDb('config_name') !!}">
                    <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{!! getConfigDb('config_name') !!}">
                </a>
            </div>
            <div class="mobile-menu-close close-style-wrap close-style-position-inherit">
                <button class="close-style search-close">
                    <i class="icon-top"></i>
                    <i class="icon-bottom"></i>
                </button>
            </div>
        </div>
        <div class="mobile-header-content-area">
            <div class="mobile-search search-style-3 mobile-header-border">
                <form action="/san-pham" method="get">
                    <input type="text" placeholder="Tìm sản phẩm...">
                    <button type="submit"><i class="fi-rs-search"></i></button>
                </form>
            </div>
            <div class="mobile-menu-wrap mobile-header-border">
                @foreach ($menus as $item)
                    {!! $item['mobile'] !!}
                @endforeach
            </div>
            <div class="mobile-header-info-wrap">
                <div class="single-mobile-header-info">
                    @if (auth()->check())
                        <a href="{{ routeArea('account.index') }}" title="{!! auth()->user()->full_name !!}"><i
                                class="fi-rs-user"></i>
                            Xin chào {!! auth()->user()->full_name !!}
                        </a>
                    @else
                        <a href="{{ routeArea('auth.login') }}" title="Đăng nhập / Đăng ký"><i
                                class="fi-rs-user"></i>
                            Đăng nhập / Đăng ký
                        </a>
                    @endif
                </div>
                <div class="single-mobile-header-info">
                    <a href="{{ routeArea('contact.index') }}" title="Liên hệ chúng tôi"><i
                            class="fi-rs-marker"></i>
                        Liên hệ chúng tôi
                    </a>
                </div>
                <div class="single-mobile-header-info">
                    <a href="tel:{{ getConfigDb('config_telephone') }}"
                        title="{{ getConfigDb('config_telephone') }}">
                        <i class="fi-rs-headphones"></i>
                        Hotline: {{ getConfigDb('config_telephone') }}
                    </a>
                </div>
                @if (auth()->check())
                    <div class="single-mobile-header-info">
                        <a href="{{ routeArea('account.logout') }}" title="Đăng xuất">
                            <i class="fi-rs-sign-out"></i>
                            Đăng xuất
                        </a>
                    </div>
                @endif
            </div>
            <div class="mobile-social-icon mb-50">
                <h6 class="mb-15">Theo dõi chúng tôi</h6>
                <a href="{{ getConfigDb('config_facebook') }}" title="facebook">
                    <img src="/web/images/theme/icons/icon-facebook-white.svg" alt="facebook">
                </a>
                <a href="{{ getConfigDb('config_instagram') }}" title="instagram">
                    <img src="/web/images/theme/icons/icon-instagram-white.svg" alt="">
                </a>
                <a href="{!! getConfigDb('config_tiktok') !!}" title="tiktok">
                    <img src="/web/images/theme/icons/icons8-tiktok.svg" alt="tiktok">
                </a>
                <a href="{!! getConfigDb('config_threads') !!}" title="threads">
                    <img src="/web/images/theme/icons/icons8-threads.svg" alt="threads">
                </a>
            </div>
            <div class="site-copyright">Copyright © 2021 DAgencyVn.net</div>
        </div>
    </div>
</div>
