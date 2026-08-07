@php
    $demoMenu = [
        ['title' => 'Điện tử', 'url' => route('product.getList'), 'children' => [
            ['title' => 'Điện thoại', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Điện thoại phổ thông', 'url' => route('product.getList')],
                ['title' => 'Điện thoại cao cấp', 'url' => route('product.getList')],
                ['title' => 'Phụ kiện điện thoại', 'url' => route('product.getList')],
            ]],
            ['title' => 'Laptop & máy tính', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Laptop văn phòng', 'url' => route('product.getList')],
                ['title' => 'Laptop gaming', 'url' => route('product.getList')],
                ['title' => 'Màn hình', 'url' => route('product.getList')],
            ]],
            ['title' => 'Tai nghe & loa', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Tai nghe không dây', 'url' => route('product.getList')],
                ['title' => 'Loa bluetooth', 'url' => route('product.getList')],
            ]],
            ['title' => 'Phụ kiện', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Sạc & cáp', 'url' => route('product.getList')],
                ['title' => 'Chuột & bàn phím', 'url' => route('product.getList')],
            ]],
        ]],
        ['title' => 'Thời trang', 'url' => route('product.getList'), 'children' => [
            ['title' => 'Thời trang nữ', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Áo', 'url' => route('product.getList')],
                ['title' => 'Váy đầm', 'url' => route('product.getList')],
                ['title' => 'Quần', 'url' => route('product.getList')],
            ]],
            ['title' => 'Thời trang nam', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Áo sơ mi', 'url' => route('product.getList')],
                ['title' => 'Quần âu', 'url' => route('product.getList')],
            ]],
            ['title' => 'Giày dép', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Giày thể thao', 'url' => route('product.getList')],
                ['title' => 'Giày cao gót', 'url' => route('product.getList')],
            ]],
            ['title' => 'Túi ví & đồng hồ', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Túi xách', 'url' => route('product.getList')],
                ['title' => 'Đồng hồ', 'url' => route('product.getList')],
            ]],
        ]],
        ['title' => 'Nhà cửa & đời sống', 'url' => route('product.getList'), 'children' => [
            ['title' => 'Đồ gia dụng', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Nồi chiên không dầu', 'url' => route('product.getList')],
                ['title' => 'Máy hút bụi', 'url' => route('product.getList')],
            ]],
            ['title' => 'Nội thất', 'url' => route('product.getList'), 'children' => [
                ['title' => 'Bàn ghế', 'url' => route('product.getList')],
                ['title' => 'Kệ tủ', 'url' => route('product.getList')],
            ]],
            ['title' => 'Bếp', 'url' => route('product.getList')],
            ['title' => 'Trang trí', 'url' => route('product.getList')],
        ]],
        ['title' => 'Làm đẹp', 'url' => route('product.getList'), 'children' => [
            ['title' => 'Chăm sóc da', 'url' => route('product.getList')],
            ['title' => 'Trang điểm', 'url' => route('product.getList')],
            ['title' => 'Chăm sóc tóc', 'url' => route('product.getList')],
            ['title' => 'Nước hoa', 'url' => route('product.getList')],
        ]],
        ['title' => 'Mẹ & bé', 'url' => route('product.getList')],
        ['title' => 'Thể thao', 'url' => route('product.getList')],
        ['title' => 'Bách hoá', 'url' => route('product.getList')],
    ];

    $nav = $demoMenu;
@endphp

<div x-data="{ mobileOpen: false }">

    <div class="bg-ink text-white text-[13px]">
        <div class="mx-auto max-w-[1320px] px-5 h-9 flex items-center justify-between gap-4">
            <p class="font-medium truncate flex items-center gap-4">
                @if (getConfigDb('config_telephone'))
                    <span>Hotline: <strong>{{ getConfigDb('config_telephone') }}</strong></span>
                @endif
                @if (getConfigDb('config_opening_time'))
                    <span class="hidden md:inline text-white/70">{{ getConfigDb('config_opening_time') }}</span>
                @endif
            </p>
            <div class="flex items-center gap-1 shrink-0">
                <a href="{{ route('order.search') }}" class="hidden lg:block px-2 text-white/70 hover:text-white">
                    Tra cứu đơn hàng
                </a>

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                        class="h-7 px-2.5 rounded-lg hover:bg-white/10 flex items-center gap-1.5 font-medium transition">
                        <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="1.7"
                            viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
                        </svg>
                        <span class="uppercase">{{ $currentLocale }}</span>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                        class="absolute right-0 top-full mt-1.5 w-44 bg-white text-ink rounded-xl border border-line shadow-lg py-1.5 z-[60]">
                        @foreach ($languages as $language)
                            @php $code = strtolower((string) ($language->code ?? '')); @endphp
                            <a href="{{ route('locale.language', $language->code) }}"
                                class="px-3.5 py-2 flex items-center justify-between text-[14px] hover:bg-canvas transition {{ $code === $currentLocale ? 'font-semibold text-brand' : '' }}">
                                <span>{{ $language->title ?? strtoupper((string) $language->code) }}</span>
                                <span class="text-[11px] text-ink-3 uppercase">{{ $language->code }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                        class="h-7 px-2.5 rounded-lg hover:bg-white/10 flex items-center gap-1.5 font-medium transition">
                        <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="1.7"
                            viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M14.5 9.5a2.5 2.5 0 0 0-5 0c0 3 5 1.5 5 5a2.5 2.5 0 0 1-5 0M12 6v12" />
                        </svg>
                        <span>{{ $currentCurrency->code }}</span>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                        class="absolute right-0 top-full mt-1.5 w-40 bg-white text-ink rounded-xl border border-line shadow-lg py-1.5 z-[60]">
                        @foreach ($currencies as $currency)
                            <a href="{{ route('locale.currency', $currency->code) }}"
                                class="px-3.5 py-2 flex items-center gap-2 text-[14px] hover:bg-canvas transition {{ $currency->code === $currentCurrency->code ? 'font-semibold text-brand' : '' }}">
                                <span>{{ $currency->code }}</span>
                                <span class="text-ink-3">{{ $currency->symbol_right ?: $currency->symbol_left }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @if (\App\Helpers\ThemeManager::shouldShowSwitcher())
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                            class="h-7 px-2.5 rounded-lg hover:bg-white/10 flex items-center gap-1.5 font-medium transition">
                            <svg class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="1.7"
                                viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 3a9 9 0 0 0 0 18 4.5 4.5 0 0 0 0-9 2.25 2.25 0 0 1 0-4.5A4.5 4.5 0 0 0 12 3Z" />
                            </svg>
                            <span>Giao diện</span>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                            class="absolute right-0 top-full mt-1.5 w-64 bg-white text-ink rounded-xl border border-line shadow-lg py-1.5 z-[60]">
                            <p class="px-3.5 pb-1.5 mb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-3 border-b border-line">
                                Xem thử giao diện
                            </p>
                            @foreach (\App\Helpers\ThemeManager::options() as $option)
                                <a href="{{ \App\Helpers\ThemeManager::urlWithTheme($option['slug']) }}"
                                    class="px-3.5 py-2 flex items-center gap-2.5 text-[14px] hover:bg-canvas transition {{ $option['active'] ? 'font-semibold text-brand' : '' }}">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                        style="background:{{ $option['swatch'] }}"></span>
                                    <span class="flex-1">{{ $option['label'] }}</span>
                                    @if ($option['active'])
                                        <span class="text-[11px] text-ink-3">Đang xem</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <header class="sticky top-0 z-50 bg-canvas/95 backdrop-blur border-b border-line">
        <div class="mx-auto max-w-[1320px] px-5 h-[72px] flex items-center gap-4 lg:gap-6">

            <button type="button" @click="mobileOpen = true" aria-label="Menu"
                class="lg:hidden -ml-1 w-10 h-10 rounded-full hover:bg-white grid place-items-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <a href="{{ route('home') }}" title="{{ getConfigDb('config_name') }}"
                class="shrink-0 block w-[120px] lg:w-[140px]">
                <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{{ getConfigDb('config_name') }}"
                    class="h-10 w-full object-contain object-left">
            </a>

            <form action="{{ route('product.getList') }}" method="get" role="search"
                class="flex-1 max-w-[560px] relative hidden sm:block">
                <input type="search" name="filter[keyword]" value="{{ request()->input('filter.keyword') }}"
                    placeholder="Tìm sản phẩm, thương hiệu, danh mục…" autocomplete="off" aria-label="Tìm sản phẩm"
                    class="w-full h-11 pl-11 pr-24 rounded-full bg-white border border-line text-[15px]
                           placeholder:text-ink-3 focus:outline-none focus:border-ink focus:ring-4 focus:ring-ink/5 transition">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-ink-3" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" />
                </svg>
                <button type="submit"
                    class="absolute right-1.5 top-1.5 h-8 px-4 rounded-full bg-ink text-white text-[13px] font-medium hover:bg-ink-2 transition">
                    Tìm
                </button>
            </form>

            <div class="flex items-center gap-1 ml-auto">
                <a href="{{ route('account.index') }}" title="Tài khoản"
                    class="h-10 px-3 rounded-full hover:bg-white flex items-center gap-2 text-[14px] font-medium transition">
                    <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" stroke-width="1.7"
                        viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 21c0-4 3.6-6 8-6s8 2 8 6" />
                    </svg>
                    <span class="hidden lg:inline">Tài khoản</span>
                </a>

                <a href="{{ route('account.wishlist') }}" title="Yêu thích"
                    class="h-10 w-10 rounded-full hover:bg-white grid place-items-center relative transition">
                    <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" stroke-width="1.7"
                        viewBox="0 0 24 24">
                        <path d="M12 20s-7-4.6-7-9.3A3.9 3.9 0 0 1 12 8a3.9 3.9 0 0 1 7 2.7C19 15.4 12 20 12 20Z" />
                    </svg>
                    <span data-wishlist-badge
                        class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-brand text-white text-[11px] font-bold grid place-items-center">0</span>
                </a>

                <a href="{{ route('checkout.cart') }}" title="Giỏ hàng"
                    class="h-10 pl-3 pr-4 rounded-full bg-white border border-line hover:border-ink flex items-center gap-2 transition">
                    <svg class="w-[19px] h-[19px]" fill="none" stroke="currentColor" stroke-width="1.7"
                        viewBox="0 0 24 24">
                        <path d="M5 7h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 7Z" />
                        <path d="M9 7V5.5a3 3 0 0 1 6 0V7" />
                    </svg>
                    <span data-cart-badge class="text-[13px] font-semibold tabular-nums">0</span>
                </a>
            </div>
        </div>

        <nav class="border-t border-line bg-white relative" x-data="{ open: null }" @mouseleave="open = null">
            <div class="mx-auto max-w-[1320px] px-5 h-11 flex items-center gap-1 overflow-x-auto text-[14px]">
                <a href="{{ route('product.getList') }}"
                    class="shrink-0 h-8 px-3 rounded-lg bg-ink text-white font-medium flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    Tất cả sản phẩm
                </a>

                @foreach ($nav as $i => $node)
                    @if (empty($node['children']))
                        <a href="{{ $node['url'] }}" title="{{ $node['title'] }}" @mouseenter="open = null"
                            class="shrink-0 h-8 px-3 rounded-lg hover:bg-canvas font-medium flex items-center">
                            {{ $node['title'] }}
                        </a>
                    @else
                        <button type="button" @mouseenter="open = {{ $i }}" @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="shrink-0 h-8 px-3 rounded-lg hover:bg-canvas font-medium flex items-center gap-1.5 transition"
                            :class="open === {{ $i }} && 'bg-canvas text-brand'">
                            {{ $node['title'] }}
                            <svg class="w-3.5 h-3.5 transition-transform" :class="open === {{ $i }} && 'rotate-180'"
                                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    @endif
                @endforeach
            </div>

            @foreach ($nav as $i => $node)
                @if (!empty($node['children']))
                    <div x-show="open === {{ $i }}" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="absolute left-0 right-0 top-full bg-white border-t border-line shadow-xl z-50">
                        <div class="mx-auto max-w-[1320px] px-5 py-7 grid grid-cols-2 md:grid-cols-4 gap-x-7 gap-y-6">
                            @foreach ($node['children'] as $child)
                                <div>
                                    <a href="{{ $child['url'] }}"
                                        class="block text-[14px] font-bold mb-3 hover:text-brand transition">
                                        {{ $child['title'] }}
                                    </a>
                                    @if (!empty($child['children']))
                                        <ul class="space-y-2 text-[14px] text-ink-2 list-none p-0 m-0">
                                            @foreach ($child['children'] as $leaf)
                                                <li>
                                                    <a href="{{ $leaf['url'] }}" class="hover:text-brand transition">
                                                        {{ $leaf['title'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>
    </header>

    <div x-show="mobileOpen" x-cloak x-transition.opacity class="fixed inset-0 bg-ink/50 z-[70]"
        @click="mobileOpen = false"></div>
    <aside x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 w-[300px] bg-white z-[80] p-5 overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <a href="{{ route('home') }}" class="block w-[110px]">
                <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{{ getConfigDb('config_name') }}"
                    class="h-8 w-full object-contain object-left">
            </a>
            <button type="button" @click="mobileOpen = false" aria-label="Đóng"
                class="w-9 h-9 rounded-full hover:bg-canvas grid place-items-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>
        </div>
        <form action="{{ route('product.getList') }}" method="get" role="search" class="mb-5">
            <input type="search" name="filter[keyword]" placeholder="Tìm sản phẩm…" aria-label="Tìm sản phẩm"
                class="w-full h-10 px-4 rounded-xl bg-canvas border border-line text-[14px] focus:outline-none focus:border-ink">
        </form>
        <div x-data="{ sub: null }">
            @foreach ($nav as $i => $node)
                <div class="border-b border-line">
                    @if (empty($node['children']))
                        <a href="{{ $node['url'] }}" class="block py-2.5 text-[15px] font-medium">{{ $node['title'] }}</a>
                    @else
                        <button type="button" @click="sub = sub === {{ $i }} ? null : {{ $i }}"
                            class="w-full py-2.5 text-[15px] font-medium flex items-center justify-between">
                            <span>{{ $node['title'] }}</span>
                            <svg class="w-4 h-4 transition-transform" :class="sub === {{ $i }} && 'rotate-180'"
                                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div x-show="sub === {{ $i }}" x-cloak class="pb-3 pl-3 space-y-2">
                            @foreach ($node['children'] as $child)
                                <a href="{{ $child['url'] }}"
                                    class="block text-[14px] text-ink-2 hover:text-brand">{{ $child['title'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </aside>
</div>

@include('web::share._badge_script')
