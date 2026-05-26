@section('script_header')
    <script type="text/javascript">
            @php
                $reqPriceSpecialGteq = array_get(request()->get('product_special', []), 'price_gteq', 130000);
                $reqPriceSpecialLteq = array_get(request()->get('product_special', []), 'price_lteq', 1000000);
                $reqProductGt = array_get(request()->get('product', []), 'quantity_gt', '');
                $reqProductLteq = array_get(request()->get('product', []), 'quantity_lteq_or_quantity_isnull', '');
                $reqFilter = array_get(request()->get('product_filter', []), 'filter_value_id_in', []);
            @endphp
        var priceGteq = {{ preg_replace('/,|\.|đ/', '', $reqPriceSpecialGteq) }};
        var priceLteq = {{ preg_replace('/,|\.|đ/', '', $reqPriceSpecialLteq) }};
    </script>
@stop
<div class="col-lg-3 primary-sidebar sticky-sidebar">
    @if(count($categories))
        <div class="sidebar-widget widget-category-2 mb-30">
            <h5 class="section-title style-1 mb-30 wow fadeIn animated">Danh mục</h5>
            <ul>
                @foreach($categories as $item)
                    @if (isset($item->categoryDescription))
                        <li class="@if(array_get(request()->get('product_category'), 'category_id_eq', '') == $item->id
                    || $item->categoryDescription->getUrlClient() == request()->url()) active @endif">
                            <a href="{{ $item->categoryDescription->getUrlClient() }}"
                               title="{!! $item->categoryDescription->title !!}">
                                {!! $item->categoryDescription->title !!}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif
    <form method="get" action="{{ request()->url() }}">
        <div class="sidebar-widget price_range range mb-30">
            <h5 class="section-title style-1 mb-30 wow fadeIn animated">Bộ lọc tìm kiếm</h5>
            <div class="price-filter">
                <div class="price-filter-inner">
                    <div id="slider-range"></div>
                    <div class="price_slider_amount">
                        <div class="label-input">
                            <span>Khoảng giá:</span>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="price_gteq"
                                   name="product_special[price_gteq]" value="{!! $reqPriceSpecialGteq !!}"
                                   style="border-radius: 10px;">
                            <span class="input-group-text">-</span>
                            <input type="text" class="form-control" id="price_lteq"
                                   name="product_special[price_lteq]" value="{!! $reqPriceSpecialLteq !!}"
                                   style="border-radius: 10px;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="list-group stock">
                <div class="list-group-item mb-10 mt-10">
                    <label class="fw-900">Kho hàng</label>
                    <div class="custome-checkbox">
                        <input class="form-check-input" type="checkbox" name="product[quantity_gt]"
                               id="inStock" value="0" @if (filled($reqProductGt)) checked @endif>
                        <label class="form-check-label"
                               for="inStock"><span>Còn hàng</span></label>
                        <br>
                        <input class="form-check-input" type="checkbox" name="product[quantity_lteq_or_quantity_isnull]"
                               id="outStock" value="0"
                               @if (filled($reqProductLteq)) checked @endif>
                        <label class="form-check-label"
                               for="outStock"><span>Hết hàng</span></label>
                        <br>
                    </div>
                </div>
            </div>
            @if(count($manufacturers))
                <div class="list-group manufacturer">
                    <div class="list-group-item mb-10 mt-10">
                        <label class="fw-900">Hãng sản xuất</label>
                        <div class="custome-checkbox">
                            @php $reqManufacturer = request()->get('manufacturer_id_in', []);@endphp
                            @foreach($manufacturers as $item)
                                <input class="form-check-input" type="checkbox" name="manufacturer_id_in[]"
                                       id="manufacturer{{ $item->id }}" value="{{ $item->id }}"
                                       @if (in_array($item->id, $reqManufacturer)) checked @endif>
                                <label class="form-check-label" for="manufacturer{{ $item->id }}">
                                    <span>{{ $item->name }}</span>
                                </label>
                                <br>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            @foreach($filters as $filter)
                @if(!isset($filter->filterDescription))
                    @continue;
                @endif
                <div class="list-group filter">
                    <div class="list-group-item mb-10 mt-10">
                        <label class="fw-900">
                            {{ $filter->filterDescription->name }}
                        </label>
                        <div class="custome-checkbox">
                            @if(count($filter->filterValues))
                                @foreach($filter->filterValues as $i => $item)
                                    <input class="form-check-input" type="checkbox"
                                           name="product_filter[filter_value_id_in][]"
                                           id="filterValue{{ $item->id }}" value="{{ $item->id }}"
                                           @if (in_array($item->id, $reqFilter)) checked @endif>
                                    <label class="form-check-label" for="filterValue{{ $item->id }}">
                                        <span>
                                            @if(isset($item->filterValueDescription))
                                                {{ $item->filterValueDescription->name }}
                                            @endif
                                        </span>
                                    </label>
                                    <br>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
            <button class="btn btn-sm btn-default" type="submit">
                <i class="fi-rs-filter mr-5"></i>Lọc
            </button>
        </div>
    </form>
    <div class="sidebar-widget product-sidebar  mb-30 p-30 bg-grey border-radius-10">
        <h5 class="section-title style-1 mb-30 wow fadeIn animated">Sản phẩm mới</h5>
        @foreach($products as $product)
            @if(!isset($product->productDescription))
                @continue;
            @endif
            @if(count($product->productSpecials))
                @php
                    $productSpecial = $product->productSpecials->sortByDesc('priority')->first();
                    $discount = round((($product->price - $productSpecial->price)/$product->price)*100);
                @endphp
            @endif
            <div class="single-post clearfix">
                <div class="image">
                    <img src="{!! $product->getImageClient() !!}"
                         alt="{!! $product->productDescription->name !!}">
                </div>
                <div class="content pt-10">
                    <h6>
                        <a href="{!! $product->productDescription->getUrlClient() !!}"
                           title="{!! $product->productDescription->name !!}">
                            {!! $product->productDescription->name !!}
                        </a>
                    </h6>
                    @if(count($product->productSpecials))
                        <div class="mb-0 mt-5">
                            <span class="price fs-6">{!! $productSpecial->getPrice() !!} </span>
                            <span class="text-decoration-line-through old-price">
                                <small>{!! $product->getPrice() !!}</small>
                            </span>
                        </div>
                    @else
                        <p class="price mb-0 mt-5">{!! $product->getPrice() !!}</p>
                    @endif
                    <div class="product-rate">
                        <img src="/client/images/stars-{!! intval(round($product->rating)) !!}.png"
                             alt="{!! $product->total_rating !!} đánh giá"/>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
