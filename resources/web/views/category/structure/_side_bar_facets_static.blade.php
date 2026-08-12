<div class="sidebar-widget price_range range mb-30">
    <h5 class="section-title style-1 mb-30 wow fadeIn animated">Bộ lọc tìm kiếm</h5>
    <div class="form-group mb-3">
        <label for="filter-keyword" class="font-black">Tìm kiếm</label>
        <input type="search" id="filter-keyword" name="filter[keyword]" class="form-control mt-2"
            placeholder="Tên sản phẩm, SKU, model..." value="" autocomplete="off">
    </div>

    <div class="price-filter">
        <div class="price-filter-inner">
            <div id="slider-range"></div>
            <div class="price_slider_amount">
                <div class="label-input"><span>Khoảng giá:</span></div>
                {{-- Bootstrap input-group → Tailwind flex inline. Giá trị do slider set. --}}
                <div class="flex items-stretch mb-3">
                    <input type="text" class="form-control rounded-r-none" id="price_gteq" name="filter[price_min]"
                        value="">
                    <span
                        class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 text-gray-600 text-sm">-</span>
                    <input type="text" class="form-control rounded-l-none border-l-0" id="price_lteq"
                        name="filter[price_max]" value="">
                </div>
            </div>
        </div>
    </div>

    <div class="list-group rating-filter">
        <div class="list-group-item mb-10 mt-10">
            <label class="font-black">Đánh giá</label>
            <div class="custome-radio space-y-1 mt-2">
                @foreach ([5, 4, 3] as $star)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="filter[rating_min]" id="rating{{ $star }}"
                            value="{{ $star }}">
                        <label class="form-check-label" for="rating{{ $star }}">
                            <img src="/web/images/stars-{{ $star }}.png" alt="{{ $star }} sao"
                                class="inline-block align-middle" style="height: 14px;">
                            <span>trở lên</span>
                        </label>
                    </div>
                @endforeach
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="filter[rating_min]" id="ratingAny" value=""
                        checked>
                    <label class="form-check-label" for="ratingAny"><span>Tất cả đánh giá</span></label>
                </div>
            </div>
        </div>
    </div>

    <div class="list-group stock">
        <div class="list-group-item mb-10 mt-10">
            <label class="font-black">Kho hàng</label>
            <div class="custome-checkbox space-y-1 mt-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="filter[in_stock][]" id="inStock"
                        value="1">
                    <label class="form-check-label" for="inStock"><span>Còn hàng</span></label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="filter[in_stock][]" id="outStock"
                        value="0">
                    <label class="form-check-label" for="outStock"><span>Hết hàng</span></label>
                </div>
            </div>
        </div>
    </div>

    @if (count($warehouses ?? []) > 1)
        <div class="list-group warehouse-filter">
            <div class="list-group-item mb-10 mt-10">
                <label class="font-black">Chi nhánh / Kho</label>
                <div class="custome-radio space-y-1 mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="filter[warehouse_id]" id="warehouseAny"
                            value="" checked>
                        <label class="form-check-label" for="warehouseAny"><span>Tất cả kho</span></label>
                    </div>
                    @foreach ($warehouses as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filter[warehouse_id]"
                                id="warehouse{{ $item->id }}" value="{{ $item->id }}">
                            <label class="form-check-label" for="warehouse{{ $item->id }}">
                                <span>{{ $item->name }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if (count($manufacturers) && !$hideManufacturer)
        <div class="list-group manufacturer">
            <div class="list-group-item mb-10 mt-10">
                <label class="font-black">Hãng sản xuất</label>
                <div class="custome-checkbox space-y-1 mt-2" style="max-height: 250px; overflow-y: auto;">
                    @foreach ($manufacturers as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="filter[manufacturer_id][]"
                                id="manufacturer{{ $item->id }}" value="{{ $item->id }}">
                            <label class="form-check-label" for="manufacturer{{ $item->id }}">
                                <span>{{ $item->name }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @foreach ($filters as $filter)
        <div class="list-group filter">
            <div class="list-group-item mb-10 mt-10">
                <label class="font-black">{{ $filter->name }}</label>
                <div class="custome-checkbox space-y-1 mt-2" style="max-height: 250px; overflow-y: auto;">
                    @foreach ($filter->filterValues as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="filter[filter_value_id][]"
                                id="filterValue{{ $item->id }}" value="{{ $item->id }}">
                            <label class="form-check-label" for="filterValue{{ $item->id }}">
                                <span>{{ $item->name }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <button class="btn btn-sm mt-4" type="submit">
        <i class="fi-rs-filter mr-5"></i>Lọc
    </button>
</div>
