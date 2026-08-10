{{--
    Mục "Giao diện" ở header — hover hiện danh sách theme, click một theme để
    xem thử trang hiện tại bằng theme đó (`ThemeManager::urlWithTheme()` giữ
    nguyên path/query hiện tại, chỉ đổi tham số `theme`).

    KHÔNG dùng lại class `cart-dropdown-wrap`/`account-dropdown` như
    _locale_switcher.blade.php bên cạnh — hai class đó có CSS riêng trong
    public/web/css/main.css:
      - `.cart-dropdown-wrap.account-dropdown { width: 200px }` — compound
        class, specificity CAO HƠN một utility Tailwind như `.w-64`, nên
        thắng bất kể thứ tự nạp file → panel bị ép hẹp, chữ vỡ dòng.
      - `.cart-dropdown-wrap` mặc định `opacity:0;visibility:hidden` và chỉ
        hiện qua CSS thuần `:hover` (`.header-action-icon-2:hover
        .cart-dropdown-wrap`), tách biệt hoàn toàn khỏi `x-show` của Alpine.
        Hai cơ chế cùng điều khiển ẩn/hiện một phần tử, đứt tay khỏi vùng
        hover (khoảng trống do margin) là y hệt "hover xong không bấm được".
    Panel dưới đây tự vẽ bằng Tailwind thuần, Alpine toàn quyền điều khiển,
    không đụng hai class đó.

    Panel áp sát trigger (`top-full`, không margin) rồi mới đệm khoảng cách
    NHÌN bằng padding-top BÊN TRONG panel — để không có khe hở giữa nút bấm
    và panel mà con trỏ phải băng qua. Băng qua khe hở ấy là lúc
    `@mouseleave` (và CSS `:hover` ở bản cũ) coi như đã rời phần tử, đóng
    dropdown giữa chừng.

    Aurora có bản riêng inline trong share/menu.blade.php (chung khối với
    dropdown Ngôn ngữ/Tiền tệ ở thanh trên) vì header đó dùng bộ token
    Tailwind riêng (--color-ink/--color-canvas).
--}}
@if (\App\Helpers\ThemeManager::shouldShowSwitcher())
    <div class="header-action-icon-2 relative" x-data="{ open: false }" @mouseenter="open = true"
        @mouseleave="open = false">
        <a href="#" class="block" title="Xem thử giao diện khác" @click.prevent="open = !open">
            <svg class="inline-block align-middle" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 3a9 9 0 0 0 0 18 4.5 4.5 0 0 0 0-9 2.25 2.25 0 0 1 0-4.5A4.5 4.5 0 0 0 12 3Z" />
            </svg>
            <span class="lable ml-0">Giao diện</span>
        </a>
        <div class="absolute right-0 top-full z-50 w-[16rem] rounded-lg border border-gray-100 bg-white pb-2 pt-3 shadow-lg"
            x-show="open" x-cloak style="display:none" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <p class="mb-1 border-b border-gray-100 px-4 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                Xem thử giao diện
            </p>
            <ul class="m-0 list-none p-0">
                @foreach (\App\Helpers\ThemeManager::options() as $option)
                    <li>
                        <a href="{{ \App\Helpers\ThemeManager::urlWithTheme($option['slug']) }}"
                            data-theme-link
                            class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 {{ $option['active'] ? 'font-semibold text-brand' : '' }}">
                            <span class="inline-block h-3 w-3 flex-shrink-0 rounded-full"
                                style="background:{{ $option['swatch'] }}"></span>
                            <span class="flex-1">{{ $option['label'] }}</span>
                            @if ($option['active'])
                                <span class="text-[11px] text-gray-400">Đang xem</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
