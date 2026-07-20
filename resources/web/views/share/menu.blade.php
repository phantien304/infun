<div x-data="{ mobileOpen: false }">
    <header class="header-area header-style-1 header-height-2">
        <div class="header-middle hidden lg:block py-4 border-b border-gray-100">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="header-wrap flex items-center justify-between gap-6">
                    <div class="logo logo-width-1 flex-shrink-0">
                        <a href="{{ route('home') }}" title="{!! getConfigDb('config_name') !!}">
                            <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{!! getConfigDb('config_name') !!}">
                        </a>
                    </div>
                    <div class="header-right flex items-center gap-6 flex-1 justify-end">
                        <div class="search-style-2 flex-1 max-w-xl">
                            <form action="{{ route('product.getList') }}" method="get" role="search">
                                <input type="search" name="filter[keyword]" class="form-control"
                                    value="{{ request()->input('filter.keyword') }}" placeholder="Tìm sản phẩm..."
                                    autocomplete="off" aria-label="Tìm sản phẩm">
                            </form>
                        </div>
                        <div class="header-action-right">
                            <div class="header-action-2 flex items-center gap-4">
                                @include('web::share._locale_switcher')
                                <div class="header-action-icon-2 relative">
                                    <a href="{!! route('account.wishlist') !!}" title="Yêu thích" class="block">
                                        <img class="svgInject" alt="Sản phẩm yêu thích"
                                            src="/web/images/theme/icons/icon-heart.svg">
                                        <span class="pro-count blue" id="count-wishlist" data-wishlist-badge>0</span>
                                    </a>
                                    <a href="{!! route('account.wishlist') !!}" title="Yêu thích">
                                        <span class="lable">Yêu thích</span>
                                    </a>
                                </div>
                                <div class="header-action-icon-2 relative">
                                    <a class="mini-cart-icon block" href="{{ route('checkout.cart') }}"
                                        title="Giỏ hàng">
                                        <img alt="Giỏ hàng" src="/web/images/theme/icons/icon-cart.svg">
                                        <span class="pro-count blue" id="cart-total" data-cart-badge>0</span>
                                    </a>
                                    <a class="mini-cart-icon" href="{{ route('checkout.cart') }}" title="Giỏ hàng">
                                        <span class="lable">Giỏ hàng</span>
                                    </a>
                                </div>
                                <div class="header-action-icon-2 relative"
                                    @if (auth()->check()) x-data="{ open: false }"
                                                       @mouseenter="open = true"
                                                       @mouseleave="open = false" @endif>
                                    <a href="{!! route('account.index') !!}" title="Tài khoản" class="block">
                                        <img class="svgInject" alt="Tài khoản"
                                            src="/web/images/theme/icons/icon-user.svg">
                                    </a>
                                    <a href="{!! route('account.index') !!}" title="Tài khoản">
                                        <span class="lable ml-0">Tài khoản</span>
                                    </a>
                                    @if (auth()->check())
                                        <div class="cart-dropdown-wrap cart-dropdown-hm2 account-dropdown absolute right-0 top-full z-50 mt-1 w-56 bg-white shadow-lg rounded-md py-2"
                                            x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0">
                                            <ul class="list-none m-0 p-0">
                                                <li>
                                                    <a href="{{ route('account.index') }}" title="Thông tin tài khoản"
                                                        class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50">
                                                        <i class="fi fi-rs-user"></i> Tài khoản của tôi
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('order.search') }}" title="Theo dõi đơn hàng"
                                                        class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50">
                                                        <i class="fi fi-rs-location-alt"></i> Theo dõi đơn hàng
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('account.wishlist') }}" title="Sản phẩm yêu thích"
                                                        class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50">
                                                        <i class="fi fi-rs-heart"></i> Sản phẩm yêu thích
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('account.logout') }}" title="Đăng xuất"
                                                        class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50">
                                                        <i class="fi fi-rs-sign-out"></i> Đăng xuất
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

        <div class="header-bottom header-bottom-bg-color sticky-bar">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="header-wrap flex items-center justify-between relative py-3">
                    <div class="logo logo-width-1 block lg:hidden">
                        <a href="{{ route('home') }}" title="{!! getConfigDb('config_name') !!}">
                            <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{!! getConfigDb('config_name') !!}">
                        </a>
                    </div>
                    <div class="header-nav hidden lg:flex">
                        <div
                            class="main-menu main-menu-padding-1 main-menu-lh-2 hidden lg:block font-heading infunstudio">
                            @foreach ($menus as $item)
                                @php echo $item['pc']; @endphp
                            @endforeach
                        </div>
                    </div>
                    <div class="hotline hidden lg:flex items-center gap-2">
                        <img src="/web/images/theme/icons/icon-headphone.svg" alt="hotline">
                        <p>{!! getConfigDb('config_telephone') !!}<span>Hỗ trợ 24/7</span></p>
                    </div>
                    <div class="header-action-icon-2 block lg:hidden">
                        <button type="button" class="burger-icon burger-icon-white" @click="mobileOpen = true"
                            aria-label="Mở menu">
                            <span class="burger-icon-top"></span>
                            <span class="burger-icon-mid"></span>
                            <span class="burger-icon-bottom"></span>
                        </button>
                    </div>
                    <div class="header-action-right block lg:hidden">
                        <div class="header-action-2 flex items-center gap-3">
                            <div class="header-action-icon-2 relative">
                                <a href="{!! route('account.wishlist') !!}" class="block">
                                    <img alt="Sản phẩm yêu thích" src="/web/images/theme/icons/icon-heart.svg">
                                    <span class="pro-count white" id="count-wishlist" data-wishlist-badge>0</span>
                                </a>
                            </div>
                            <div class="header-action-icon-2 relative">
                                <a class="mini-cart-icon block" href="{{ route('checkout.cart') }}"
                                    title="Giỏ hàng">
                                    <img alt="Giỏ hàng" src="/web/images/theme/icons/icon-cart.svg">
                                    <span class="pro-count white" id="cart-total" data-cart-badge>0</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="mobile-header-active mobile-header-wrapper-style" :class="{ 'sidebar-visible': mobileOpen }"
        x-show="mobileOpen" x-cloak x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full" style="display:none;">
        <div class="fixed inset-0 bg-black/50 z-40" @click="mobileOpen = false"></div>
        <div class="mobile-header-wrapper-inner relative z-50 bg-white h-full w-80 max-w-full overflow-y-auto">
            <div class="mobile-header-top flex items-center justify-between p-4 border-b">
                <div class="mobile-header-logo">
                    <a href="{{ route('home') }}" title="{!! getConfigDb('config_name') !!}">
                        <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{!! getConfigDb('config_name') !!}">
                    </a>
                </div>
                <button type="button" class="close-style search-close" @click="mobileOpen = false"
                    aria-label="Đóng">
                    <i class="icon-top"></i>
                    <i class="icon-bottom"></i>
                </button>
            </div>
            <div class="mobile-header-content-area p-4">
                <div class="mobile-search search-style-3 mobile-header-border mb-4">
                    <form action="{{ route('product.getList') }}" method="get" role="search">
                        <input type="search" name="filter[keyword]"
                            value="{{ request()->input('filter.keyword') }}" placeholder="Tìm sản phẩm..."
                            class="form-control" autocomplete="off" aria-label="Tìm sản phẩm">
                        <button type="submit" class="btn btn-md mt-2"><i class="fi-rs-search"></i> Tìm</button>
                    </form>
                </div>
                <div class="mobile-menu-wrap mobile-header-border mb-4">
                    @foreach ($menus as $item)
                        {!! $item['mobile'] !!}
                    @endforeach
                </div>
                <div class="mobile-header-info-wrap space-y-2">
                    <div class="single-mobile-header-info">
                        @if (auth()->check())
                            <a href="{{ route('account.index') }}" title="{!! auth()->user()->full_name !!}"
                                class="flex items-center gap-2">
                                <i class="fi-rs-user"></i> Xin chào {!! auth()->user()->full_name !!}
                            </a>
                        @else
                            <a href="{{ route('auth.login') }}" title="Đăng nhập / Đăng ký"
                                class="flex items-center gap-2">
                                <i class="fi-rs-user"></i> Đăng nhập / Đăng ký
                            </a>
                        @endif
                    </div>
                    <div class="single-mobile-header-info">
                        <a href="{{ route('contact.index') }}" title="Liên hệ chúng tôi"
                            class="flex items-center gap-2">
                            <i class="fi-rs-marker"></i> Liên hệ chúng tôi
                        </a>
                    </div>
                    <div class="single-mobile-header-info">
                        <a href="tel:{{ getConfigDb('config_telephone') }}"
                            title="{{ getConfigDb('config_telephone') }}" class="flex items-center gap-2">
                            <i class="fi-rs-headphones"></i> Hotline: {{ getConfigDb('config_telephone') }}
                        </a>
                    </div>
                    @if (auth()->check())
                        <div class="single-mobile-header-info">
                            <a href="{{ route('account.logout') }}" title="Đăng xuất"
                                class="flex items-center gap-2">
                                <i class="fi-rs-sign-out"></i> Đăng xuất
                            </a>
                        </div>
                    @endif
                </div>
                <div class="mobile-social-icon mt-8 mb-12">
                    <h6 class="mb-4">Theo dõi chúng tôi</h6>
                    <div class="flex gap-2">
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
                </div>
                <div class="site-copyright text-xs text-gray-400">Copyright © 2021 DAgencyVn.net</div>
            </div>
        </div>
    </div>
</div>
<style>
    [x-cloak] {
        display: none !important;
    }
</style>
{{-- Phương án B item 1: badge giỏ/wishlist hydrate client-side (SSR để 0) --}}
{{-- → HTML header user-agnostic, cache_page / CF Cache Everything không dính số của guest khác. --}}
<script>
    (function () {
        function setBadges(sel, value) {
            document.querySelectorAll(sel).forEach(function (el) { el.textContent = value; });
        }
        function hydrateBadges() {
            fetch('{{ route('cart.badge') }}', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (json) {
                    var d = (json && json.data) || null;
                    if (!d) return;
                    setBadges('[data-cart-badge]', d.cart);
                    setBadges('[data-wishlist-badge]', d.wishlist);
                })
                .catch(function () { /* im lặng — badge giữ 0 */ });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hydrateBadges);
        } else {
            hydrateBadges();
        }
    })();
</script>
