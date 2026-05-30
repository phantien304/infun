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
@endphp

<div class="col-lg-3 primary-sidebar sticky-sidebar">
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
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="price_gteq" name="filter[price_min]"
                                value="{{ request()->input('filter.price_min') }}" style="border-radius: 10px;">
                            <span class="input-group-text">-</span>
                            <input type="text" class="form-control" id="price_lteq" name="filter[price_max]"
                                value="{{ request()->input('filter.price_max') }}" style="border-radius: 10px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="list-group stock">
                <div class="list-group-item mb-10 mt-10">
                    <label class="fw-900">Kho hàng</label>
                    <div class="custome-checkbox">
                        <input class="form-check-input" type="checkbox" name="filter[in_stock][]" id="inStock"
                            value="1" @checked(in_array('1', $inStock, true))>
                        <label class="form-check-label" for="inStock"><span>Còn hàng</span></label><br>
                        <input class="form-check-input" type="checkbox" name="filter[in_stock][]" id="outStock"
                            value="0" @checked(in_array('0', $inStock, true))>
                        <label class="form-check-label" for="outStock"><span>Hết hàng</span></label><br>
                    </div>
                </div>
            </div>

            @if (count($manufacturers))
                <div class="list-group manufacturer">
                    <div class="list-group-item mb-10 mt-10">
                        <label class="fw-900">Hãng sản xuất</label>
                        <div class="custome-checkbox">
                            @foreach ($manufacturers as $item)
                                <input class="form-check-input" type="checkbox" name="filter[manufacturer_id][]"
                                    id="manufacturer{{ $item->id }}" value="{{ $item->id }}"
                                    @checked(in_array($item->id, $reqManufacturer))>
                                <label class="form-check-label" for="manufacturer{{ $item->id }}">
                                    <span>{{ $item->name }}</span>
                                </label><br>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @foreach ($filters as $filter)
                <div class="list-group filter">
                    <div class="list-group-item mb-10 mt-10">
                        <label class="fw-900">{{ $filter->name }}</label>
                        <div class="custome-checkbox">
                            @foreach ($filter->filterValues as $item)
                                <input class="form-check-input" type="checkbox" name="filter[filter_value_id][]"
                                    id="filterValue{{ $item->id }}" value="{{ $item->id }}"
                                    @checked(in_array($item->id, $reqFilter))>
                                <label class="form-check-label" for="filterValue{{ $item->id }}">
                                    <span>
                                        {{ $item->name }}
                                    </span>
                                </label><br>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            <button class="btn btn-sm btn-default" type="submit">
                <i class="fi-rs-filter mr-5"></i>Lọc
            </button>
        </div>
    </form>

    <div class="sidebar-widget product-sidebar mb-30 p-30 bg-grey border-radius-10">
        <h5 class="section-title style-1 mb-30 wow fadeIn animated">Sản phẩm mới</h5>
        @foreach ($products as $product)
            <div class="single-post clearfix">
                <div class="image">
                    <img src="{{ $product->thumbnail(50, 50) }}" alt="{{ $product->name }}">
                </div>
                <div class="content pt-10">
                    <h6>
                        <a href="{{ $product->url }}" title="{{ $product->name }}">{{ $product->name }}</a>
                    </h6>
                    @if ($product->productSpecial)
                        <div class="mb-0 mt-5">
                            <span class="price fs-6">{{ $product->productSpecial->pricePromotionLabel }}</span>
                            <span class="text-decoration-line-through old-price">
                                <small>{{ $product->productSpecial->priceRegularLabel }}</small>
                            </span>
                        </div>
                    @else
                        <p class="price mb-0 mt-5">{{ $product->priceLabel }}</p>
                    @endif
                    <div class="product-rate">
                        <img src="/web/images/stars-{{ (int) round($product->rating) }}.png"
                            alt="{{ $product->totalRating }} đánh giá" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
