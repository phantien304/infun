@if(isset($product->productDescription))
    @php
        $filterName = [];
        if (isset($reqFilter) && count($reqFilter)) {
            foreach ($product->productFilters as $filters){
                if(isset($filters->filterValue->filterValueDescription)){
                    $filterName[] = $filters->filterValue->filterValueDescription->name;
                }
            }
        }
    @endphp
    @if(count($product->productSpecials))
        @php
            $productSpecial = $product->productSpecials->sortByDesc('priority')->first();
            $discount = round((($product->price - $productSpecial->price)/$product->price)*100);
        @endphp
    @endif
    <div class="col-lg-3 col-md-4 col-12 col-sm-6">
        <div class="product-cart-wrap mb-30">
            <div class="product-img-action-wrap">
                <div class="product-img product-img-zoom">
                    <a href="{!! $product->productDescription->getUrlClient() !!}"
                       title="{!! $product->productDescription->name !!}">
                        <img src="{!! $product->getImageClient() !!}" alt="{!! $product->productDescription->name !!}"
                             class="default-img">
                        <img src="{!! $product->getImageClient() !!}" alt="{!! $product->productDescription->name !!}"
                             class="hover-img">
                    </a>
                </div>
                @if(count($product->productSpecials))
                    <div class="product-badges product-badges-position product-badges-mrg">
                        <span class="sale">-{{ $discount }}%</span>
                    </div>
                @else
                    @if(filled($product->badge))
                        <div class="product-badges product-badges-position product-badges-mrg">
                        <span class="{{ $product->badge }}">
                            {!! getInfunStudioConfig('product.badge.'.$product->badge) !!}
                        </span>
                        </div>
                    @endif
                @endif
            </div>
            @if(isset($product->manufacturer))
                <div class="align-self-center d-block d-lg-none">
                    <div class="img-manufacture justify-content-center">
                        <img alt="{!! $product->manufacturer->name !!}"
                             src="{!! $product->manufacturer->getImageClient(90, 43) !!}"
                             class="center-block d-block mx-auto"/>
                    </div>
                </div>
            @endif
            <div class="product-content-wrap">
                <div class="product-category">
                    @if (count($product->productCategories))
                        @foreach($product->productCategories as $item)
                            @if(isset($item->category->categoryDescription))
                                <span class="badge badge-pill text-wrap text-secondary ps-0">
                                    {!! $item->category->categoryDescription->title !!}
                                </span>
                            @endif
                        @endforeach
                    @endif
                </div>
                @if(count($filterName))
                    <div class="product-filter">
                    <span class="text-secondary text-decoration-underline fst-italic">
                        {!! implode(', ', $filterName) !!}
                    </span>
                    </div>
                @endif
                <h2>
                    <a href="{!! $product->productDescription->getUrlClient() !!}"
                       title="{!! $product->productDescription->name !!}">
                        {!! $product->productDescription->name !!}
                    </a>
                </h2>
                @if(isset($product->manufacturer))
                    <div class="manufacturer">
                        <span class="text-secondary">
                            {!! $product->manufacturer->name !!}
                        </span>
                    </div>
                @endif
                <div class="product-rate-cover">
                    <div class="product-rate d-inline-block">
                        <img src="/client/images/stars-{!! intval(round($product->rating)) !!}.png"
                             alt="{!! $product->total_rating !!} đánh giá"/>
                    </div>
                    <span class="font-small ml-5 text-muted"> ({!! intval(round($product->rating)) !!})</span>
                </div>
                @if($product->weight && $product->weight > 0)
                    <div>
                        <span class="font-small text-muted">{{ getWeightProduct($product) }}</span>
                    </div>
                @endif
                <div class="product-price">
                    @if(count($product->productSpecials))
                        <span>{!! $productSpecial->getPrice() !!} </span>
                        <span class="old-price">{!! $product->getPrice() !!}</span>
                    @else
                        <span>{!! $product->getPrice() !!} </span>
                    @endif
                </div>
                @if(count($product->productSpecials))
                    <div class="countdown-price d-flex justify-content-center mt-3">
                        <span class="text-light btn-brand px-2 rounded" id="countdown_{{$product->id}}"></span>
                    </div>
                    <script type="text/javascript">
                        setInterval("countDownTime('{!! $productSpecial->date_end !!}', '{{ $product->id }}')", 550);
                    </script>
                @endif
            </div>
        </div>
    </div>
@endif
