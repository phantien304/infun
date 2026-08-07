{{--
    Thẻ nhỏ góc màn hình mời chọn theme ở trang chủ — CỐ Ý không làm modal
    che toàn màn hình: đây là một gợi ý tiện, không phải bước bắt buộc trước
    khi xem trang, nên không cần lớp nền tối chặn tương tác.

    CHỈ include từ layout ở trang chủ (xem layouts/main.blade.php của base
    và aurora — bọc trong điều kiện request()->is('/')), và tự ẩn khi:
      - `theme.allow_query_override` tắt (production) — link trong thẻ đi
        qua query `?theme=`, tắt cờ thì link không còn tác dụng gì.
      - không có theme nào ngoài base (`theme.available` rỗng) — không có gì
        để chọn.
      - đang ở chế độ preview rồi (ThemeManager::isPreview() — tức đã vào
        bằng `?theme=`) — khách đã chọn một lần trong phiên này.

    Dùng Tailwind THUẦN (không đụng --color-brand/ink/canvas — token chỉ tồn
    tại ở entry CSS riêng từng theme) để một file này render đúng dù đang ở
    base hay bất kỳ theme nào.
--}}
@if (\App\Helpers\ThemeManager::shouldShowSwitcher() && ! \App\Helpers\ThemeManager::isPreview())
    <div x-data="{
            open: false,
            init() {
                try {
                    this.open = sessionStorage.getItem('infun_theme_popup_dismissed') !== '1';
                } catch (e) {
                    this.open = true;
                }
            },
            dismiss() {
                this.open = false;
                try {
                    sessionStorage.setItem('infun_theme_popup_dismissed', '1');
                } catch (e) {}
            },
        }" x-show="open" x-cloak style="display:none"
        class="fixed inset-x-4 bottom-4 z-[9999] sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-72">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-xl" x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">

            <div class="mb-3 flex items-start justify-between gap-2">
                <p class="text-sm font-bold leading-snug text-gray-900">Xem thử giao diện khác?</p>
                <button type="button" @click="dismiss()" aria-label="Đóng"
                    class="-mr-1 -mt-1 grid h-6 w-6 flex-shrink-0 place-items-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <div class="space-y-1">
                @foreach (\App\Helpers\ThemeManager::options() as $option)
                    <a href="{{ \App\Helpers\ThemeManager::urlWithTheme($option['slug'], route('home')) }}"
                        title="{{ $option['description'] }}"
                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] transition {{ $option['active'] ? 'bg-gray-50 ring-1 ring-inset ring-gray-200' : 'hover:bg-gray-50' }}">
                        <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full" style="background:{{ $option['swatch'] }}"></span>
                        <span class="flex-1 truncate font-medium text-gray-800">{{ $option['label'] }}</span>
                        @if ($option['active'])
                            <span class="flex-shrink-0 text-[11px] text-gray-400">Đang xem</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
