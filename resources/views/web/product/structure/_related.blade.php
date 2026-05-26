@if(isset($product->productDescription))
    @if(count($product->productSpecials))
        @php
            $productSpecial = $product->productSpecials->sortByDesc('priority')->first();
            $discount = round((($product->price - $productSpecial->price)/$product->price)*100);
        @endphp
    @endif
    <div class="col-lg-3 col-md-4 col-12 col-sm-6">
        <div class="product-cart-wrap hover-up">
            <div class="product-img-action-wrap">
                <div class="product-img product-img-zoom">
                    <a href="{!! $product->productDescription->getUrlClient() !!}"
                       title="{!! $product->productDescription->name !!}">
                        <img src="{!! $product->getImageClient(310, 310) !!}"
                             alt="{!! $product->productDescription->name !!}" class="default-img">
                        <img src="{!! $product->getImageClient(310, 310) !!}"
                             alt="{!! $product->productDescription->name !!}" class="hover-img">
                    </a>
                </div>
                <div class="product-badges product-badges-position product-badges-mrg">
                    <span class="hot">@if(count($product->productSpecials)) -{{ $discount }}% @else Hot @endif</span>
                </div>
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
                <div class="product-price">
                    @if(count($product->productSpecials))
                        <span>{!! $productSpecial->getPrice() !!} </span>
                        <span class="old-price">{!! $product->getPrice() !!}</span>
                    @else
                        <span>{!! $product->getPrice() !!} </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
