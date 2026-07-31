{{--
    Sidebar facet — giao diện dựng lại theo public/prototype/category-v1.html
    (thẻ rounded-2xl nền trắng, viền hairline, chữ 13.5-14px).

    KHÔNG đổi hợp đồng dữ liệu: mọi input giữ nguyên tên cũ
    (filter[keyword], filter[price_min], filter[price_max], filter[in_stock][],
    filter[manufacturer_id][], filter[filter_value_id][]) nên
    ProductRepository và script hydrate trong _side_bar.blade.php chạy y như cũ.

    MỚI: filter[rating_min] — xem ProductRepository::normalizeRating (chỉ nhận
    3/4/5) và hai chỗ áp lọc: beforeBuildForList (DB) + searchViaMeilisearch.

    LƯU Ý VẬN HÀNH: fragment này được cache qua FragmentCache::facets(). Sau
    khi deploy PHẢI xoá cache, nếu không vẫn thấy giao diện cũ:
        php artisan tinker --execute="App\View\FragmentCache::forgetFacets();"
--}}
<div class="space-y-4">

    <h5 class="text-[15px] font-bold tracking-tight">Bộ lọc tìm kiếm</h5>

    {{-- Tìm trong danh mục --}}
    <div class="relative">
        <input type="search" id="filter-keyword" name="filter[keyword]" value="" autocomplete="off"
            placeholder="Tên sản phẩm, SKU, model…"
            class="w-full h-10 pl-9 pr-3 rounded-xl bg-white border border-gray-200 text-[14px]
                   placeholder:text-gray-400 focus:outline-none focus:border-gray-900 transition">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
            stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
        </svg>
    </div>

    {{-- Khoảng giá — giữ nguyên slider jQuery UI sẵn có (#slider-range, biến
         priceGteq/priceLteq set ở _side_bar.blade.php), chỉ thay lớp vỏ. --}}
    <div class="rounded-2xl bg-white border border-gray-200 p-4">
        <label class="block text-[13.5px] font-semibold mb-3">Khoảng giá</label>
        <div class="price-filter">
            <div class="price-filter-inner">
                <div id="slider-range" class="mb-4"></div>
                <div class="price_slider_amount">
                    <div class="flex items-stretch gap-2">
                        <input type="text" id="price_gteq" name="filter[price_min]" value="" inputmode="numeric"
                            class="w-full h-9 px-3 rounded-lg bg-gray-50 border border-gray-200 text-[13.5px]
                                   focus:outline-none focus:border-gray-900 transition">
                        <span class="self-center text-gray-400 text-[13px]">–</span>
                        <input type="text" id="price_lteq" name="filter[price_max]" value="" inputmode="numeric"
                            class="w-full h-9 px-3 rounded-lg bg-gray-50 border border-gray-200 text-[13.5px]
                                   focus:outline-none focus:border-gray-900 transition">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Đánh giá — facet MỚI.
         Radio chứ không checkbox: "4 sao trở lên" đã bao hàm 5 sao, cho chọn
         nhiều mức cùng lúc là vô nghĩa và sinh nhiều URL cùng một ý nghĩa.
         Mục "Tất cả đánh giá" (value rỗng) để bỏ chọn — radio thuần HTML
         không tự bỏ được, thiếu nó người dùng lọc rồi thì kẹt luôn. --}}
    <div class="rounded-2xl bg-white border border-gray-200 p-4">
        <label class="block text-[13.5px] font-semibold mb-3">Đánh giá</label>
        <div class="space-y-1">
            @foreach ([5, 4, 3] as $star)
                <label for="rating{{ $star }}"
                    class="flex items-center gap-2.5 py-1 cursor-pointer group select-none">
                    <input class="w-4 h-4 accent-gray-900" type="radio" name="filter[rating_min]"
                        id="rating{{ $star }}" value="{{ $star }}">
                    <span class="text-[14px] text-amber-500 tracking-tight">{{ str_repeat('★', $star) }}<span
                            class="text-gray-300">{{ str_repeat('★', 5 - $star) }}</span></span>
                    <span class="text-[13px] text-gray-600 group-hover:text-gray-900 transition">trở lên</span>
                </label>
            @endforeach
            <label for="ratingAny" class="flex items-center gap-2.5 py-1 cursor-pointer group select-none">
                <input class="w-4 h-4 accent-gray-900" type="radio" name="filter[rating_min]" id="ratingAny"
                    value="" checked>
                <span class="text-[13.5px] text-gray-600 group-hover:text-gray-900 transition">Tất cả đánh giá</span>
            </label>
        </div>
    </div>

    {{-- Tình trạng kho --}}
    <div class="rounded-2xl bg-white border border-gray-200 p-4">
        <label class="block text-[13.5px] font-semibold mb-3">Kho hàng</label>
        <div class="space-y-1">
            <label for="inStock" class="flex items-center gap-2.5 py-1 cursor-pointer group select-none">
                <input class="w-4 h-4 rounded accent-gray-900" type="checkbox" name="filter[in_stock][]" id="inStock"
                    value="1">
                <span class="text-[14px] group-hover:text-gray-900 transition">Còn hàng</span>
            </label>
            <label for="outStock" class="flex items-center gap-2.5 py-1 cursor-pointer group select-none">
                <input class="w-4 h-4 rounded accent-gray-900" type="checkbox" name="filter[in_stock][]"
                    id="outStock" value="0">
                <span class="text-[14px] group-hover:text-gray-900 transition">Hết hàng</span>
            </label>
        </div>
    </div>

    {{-- Hãng sản xuất --}}
    @if (count($manufacturers) && !$hideManufacturer)
        <div class="rounded-2xl bg-white border border-gray-200 p-4">
            <label class="block text-[13.5px] font-semibold mb-3">Hãng sản xuất</label>
            <div class="space-y-1 max-h-64 overflow-y-auto pr-1">
                @foreach ($manufacturers as $item)
                    <label for="manufacturer{{ $item->id }}"
                        class="flex items-center gap-2.5 py-1 cursor-pointer group select-none">
                        <input class="w-4 h-4 rounded accent-gray-900 shrink-0" type="checkbox"
                            name="filter[manufacturer_id][]" id="manufacturer{{ $item->id }}"
                            value="{{ $item->id }}">
                        <span class="text-[14px] group-hover:text-gray-900 transition">{{ $item->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Facet động từ bảng filter / filter_value --}}
    @foreach ($filters as $filter)
        <div class="rounded-2xl bg-white border border-gray-200 p-4">
            <label class="block text-[13.5px] font-semibold mb-3">{{ $filter->name }}</label>
            <div class="space-y-1 max-h-64 overflow-y-auto pr-1">
                @foreach ($filter->filterValues as $item)
                    <label for="filterValue{{ $item->id }}"
                        class="flex items-center gap-2.5 py-1 cursor-pointer group select-none">
                        <input class="w-4 h-4 rounded accent-gray-900 shrink-0" type="checkbox"
                            name="filter[filter_value_id][]" id="filterValue{{ $item->id }}"
                            value="{{ $item->id }}">
                        <span class="text-[14px] group-hover:text-gray-900 transition">{{ $item->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    <button type="submit"
        class="w-full h-11 rounded-2xl bg-gray-900 text-white font-semibold text-[14.5px]
               hover:bg-gray-700 transition flex items-center justify-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 6h16M7 12h10M10 18h4" />
        </svg>
        Lọc
    </button>
</div>
