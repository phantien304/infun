@section('script_header')
    <script type="text/javascript">
        var priceGteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_min', 130000)) }};
        var priceLteq = {{ (int) preg_replace('/[^\d]/', '', request()->input('filter.price_max', 1000000)) }};
    </script>
@stop

@php
    $currentCategoryId = request()->input('filter.category_id');
    $reqManufacturer = (array) request()->input('filter.manufacturer_id', []);
    $reqFilter = (array) request()->input('filter.filter_value_id', []);
    $inStock = array_map('strval', (array) request()->input('filter.in_stock', []));
    // Manufacturer landing pages already constrain by manufacturer at the
    // controller level — hide the facet to avoid double-filtering UX.
    $hideManufacturer = $hideManufacturer ?? false;
@endphp

{{--
    Sidebar filter — KHÔNG còn col-lg-3 vì wrapper grid lo column.
    Class theme `.primary-sidebar`, `.sticky-sidebar`, `.sidebar-widget`,
    `.widget-category-2`, `.section-title.style-1`, `.price-filter*`,
    `.list-group*`, `.bg-grey.border-radius-10`, `.single-post.clearfix`
    styled trong main.css.

    Form-control / form-check-input / form-check-label đã có ở Tailwind
    component layer (app.css) → render đúng dù không có Bootstrap.
--}}
<div class="primary-sidebar sticky-sidebar">
    @if (count($categories))
        <div class="sidebar-widget widget-category-2 mb-30">
            <h5 class="section-title style-1 mb-30 wow fadeIn animated">Danh mục</h5>
            <ul>
                @foreach ($categories as $item)
                    <li class="@if ($currentCategoryId == $item->id || $item->url == request()->url()) active @endif">
                        <a href="{{ $item->url }}" title="{!! $item->title !!}">
                            {!! $item->title !!}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="get" action="{{ request()->url() }}">
        @foreach (collect(request()->query())->except(['filter', 'page'])->dot() as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <div class="sidebar-widget price_range range mb-30">
            <h5 class="section-title style-1 mb-30 wow fadeIn animated">Bộ lọc tìm kiếm</h5>

            <div class="price-filter">
                <div class="price-filter-inner">
                    <div id="slider-range"></div>
                    <div class="price_slider_amount">
                        <div class="label-input"><span>Khoảng giá:</span></div>
                        {{-- Bootstrap input-group → Tailwind flex inline. --}}
                        <div class="flex items-stretch mb-3">
                            <input type="text" class="form-control rounded-r-none" id="price_gteq"
                                name="filter[price_min]" value="{{ request()->input('filter.price_min') }}">
                            <span
                                class="inline-flex items-center px-3 bg-gray-100 border border-l-0 border-gray-300 text-gray-600 text-sm">-</span>
                            <input type="text" class="form-control rounded-l-none border-l-0" id="price_lteq"
                                name="filter[price_max]" value="{{ request()->input('filter.price_max') }}">
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
                                value="1" @checked(in_array('1', $inStock, true))>
                            <label class="form-check-label" for="inStock"><span>Còn hàng</span></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="filter[in_stock][]" id="outStock"
                                value="0" @checked(in_array('0', $inStock, true))>
                            <label class="form-check-label" for="outStock"><span>Hết hàng</span></label>
                        </div>
                    </div>
                </div>
            </div>

            @if (count($manufacturers) && ! $hideManufacturer)
                <div class="list-group manufacturer">
                    <div class="list-group-item mb-10 mt-10">
                        <label class="font-black">Hãng sản xuất</label>
                        <div class="custome-checkbox space-y-1 mt-2">
                            @foreach ($manufacturers as $item)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="filter[manufacturer_id][]"
                                        id="manufacturer{{ $item->id }}" value="{{ $item->id }}"
                                        @checked(in_array($item->id, $reqManufacturer))>
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
                        <div class="custome-checkbox space-y-1 mt-2">
                            @foreach ($filter->filterValues as $item)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="filter[filter_value_id][]"
                                        id="filterValue{{ $item->id }}" value="{{ $item->id }}"
                                        @checked(in_array($item->id, $reqFilter))>
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
    </form>

    <div class="sidebar-widget product-sidebar mb-30 p-30 bg-grey border-radius-10">
        <h5 class="section-title style-1 mb-30 wow fadeIn animated">Sản phẩm mới</h5>
        @foreach ($latestProducts as $product)
            <div class="single-post flex gap-3 mb-3 clearfix">
                <div class="image flex-shrink-0">
                    <img src="{{ $product->thumbnail(50, 50) }}" alt="{{ $product->name }}">
                </div>
                <div class="content pt-10">
                    <h6>
                        <a href="{!! $product->url !!}" title="{!! $product->name !!}">
                            {!! $product->name !!}
                        </a>
                    </h6>
                    @if (filled($product->productVariantSpecial))
                        <div class="mb-0 mt-5">
                            <span class="price fs-6">{!! $product->productVariantSpecial->pricePromotionLabel !!}</span>
                            <span class="text-decoration-line-through old-price">
                                <small>{!! $product->productVariantSpecial->priceRegularLabel !!}</small>
                            </span>
                        </div>
                    @else
                        <p class="price mb-0 mt-5">{!! $product->priceLabel !!}</p>
                    @endif
                    <div class="product-rate">
                        <img src="/web/images/stars-{!! intval(round($product->ratingAvg)) !!}.png"
                            alt="{!! $product->reviewCount !!} đánh giá" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
