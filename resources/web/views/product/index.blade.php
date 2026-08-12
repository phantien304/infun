@php
    $special = $entity->productVariantSpecial;
    $priceFinal = $defaultVariant['price'] ?? ($special?->pricePromotion ?: $entity->price);
    $basePriceForDiscount = (float) $entity->price;
    $currentCurrency = app(\App\Services\Currency\CurrencyService::class)->currentCurrency();
@endphp
@section('script_header')
    <script type="text/javascript">
        var options = {!! json_encode($productOptions) !!};
        var variantMatrix = {!! json_encode($variantMatrix ?? []) !!};
        var defaultVariant = {!! json_encode($defaultVariant) !!};
        var variantGallery = {!! json_encode($variantGallery ?? []) !!};
        var productGallery = {!! json_encode(
            collect($productImages)->map(
                    fn($im) => [
                        'full' => thumbnail($im['image'], 1000, 1000),
                        'thumb' => thumbnail($im['image'], 147, 147),
                        'alt' => $im['alt'] ?? '',
                    ],
                )->values(),
        ) !!};
        var urlUserWishlist = '{{ route('account.userWishlist') }}';
        var priceProduct = {{ $priceFinal }};
        var productBasePrice = {{ $basePriceForDiscount }};
        // Giá biến thể (priceProduct) luôn là số ở đơn vị tiền tệ gốc (base_code) —
        // style.js#formatPriceLabel() cần quy đổi + format theo tiền tệ khách đang chọn
        // thay vì hardcode "đ", nếu không giá trên trang chi tiết sẽ không đổi theo
        // currency switcher dù trang danh sách đã đổi đúng (server-render qua money()).
        var currentCurrency = {
            value: {{ (float) ($currentCurrency->value ?: 1) }},
            decimalPlace: {{ (int) ($currentCurrency->decimal_place ?? 0) }},
            decimalSeparator: {!! json_encode((string) getCoreConfig('currency.decimal_separator', '.')) !!},
            thousandSeparator: {!! json_encode((string) getCoreConfig('currency.thousand_separator', ',')) !!},
            symbolLeft: {!! json_encode(trim((string) ($currentCurrency->symbol_left ?? ''))) !!},
            symbolRight: {!! json_encode(trim((string) ($currentCurrency->symbol_right ?? ''))) !!}
        };
    </script>
@stop
@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! $entity->url !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $entity->thumbnail(800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $entity->dateAvailable !!}" itemprop="datePublished" name="pubdate" />
    <meta content="{!! $entity->modifiedDate !!}" itemprop="dateModified" name="lastmod" />
    <meta content="{!! $entity->publishedDate !!}" itemprop="dateCreated" />
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! $entity->url !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $entity->thumbnail(800, 354) !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@@context":"https://schema.org/","@@type":"Product","url":"{!! $entity->url !!}","image":"{!! $entity->thumbnail(540, 540) !!}","name":"{!! $titleSeo !!}","description":"{!! $descriptionSeo !!}","sku":"{!! $entity->sku !!}","aggregateRating":{"@@type":"AggregateRating","ratingValue":"{!! $entity->ratingAvg !!}","reviewCount":"{!! $entity->reviewCount !!}"},"brand":{"@@type":"Brand","name":"{{ $entity->manufacturer?->name ?? getConfigDb('config_name') }}"},"offers":{"@@type":"Offer","url":"{!! $entity->url !!}","seller":{"@@type":"Organization","name":"{{ getConfigDb('config_name') }}","url":"{{ route('home') }}","telephone":"{{ getConfigDb('config_telephone') }}","email":"{{ getConfigDb('config_email') }}","address":"{{ getConfigDb('config_address') }}"},"itemCondition":"https://schema.org/NewCondition","availability":"https://schema.org/InStock","priceValidUntil":"{!! $entity->dateAvailable !!}","priceCurrency":"VND","price":{!! $priceFinal !!}}}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb_v2')
    <div class="container mx-auto max-w-7xl px-4 mb-30" x-data="{ contactOpen: false, tab: 'description' }">
        <div class="w-full m-auto">
            <div class="product-detail accordion-detail">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-50 mt-30">
                    <div class="mb-30">
                        @include('web::product.structure.image', ['images' => $productImages])
                    </div>
                    <div class="mb-md-0 mb-sm-5">
                        <div class="detail-info pr-30 pl-30">
                            @php
                                $currentPrice = $defaultVariant['price'] ?? ($special?->pricePromotion ?? 0);
                                $refPrice = $defaultVariant['regular_price'] ?? $basePriceForDiscount;
                                $initDiscount =
                                    $refPrice > 0 && $currentPrice > 0 && $currentPrice < $refPrice
                                        ? (int) round((($refPrice - $currentPrice) / $refPrice) * 100)
                                        : 0;
                            @endphp
                            <span class="stock-status out-stock" id="discount-badge"
                                style="{{ $initDiscount > 0 ? '' : 'display:none;' }}">
                                Tiết kiệm -<span id="discount-badge-value">{{ $initDiscount }}</span>%
                            </span>
                            <h1>{{ $entity->name }}</h1>
                            @php
                                $ratingFloat = (float) $entity->ratingAvg;
                                $ratingPct = max(0, min(100, $ratingFloat * 20));
                            @endphp
                            <div class="flex flex-wrap items-center gap-3 mt-2 mb-20 text-base">
                                <div itemtype="http://data-vocabulary.org/Review-aggregate" itemscope itemprop="review"
                                    class="inline-flex items-center gap-2">
                                    <span class="rating-stars"
                                        style="position: relative; display: inline-block; font-size: 18px; line-height: 1; letter-spacing: 2px;">
                                        <span style="color: #d4d4d4;">
                                            <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i
                                                class="fa fa-star"></i><i class="fa fa-star"></i>
                                        </span>
                                        <span
                                            style="position: absolute; top: 0; left: 0; width: {{ $ratingPct }}%; color: #ee4d2d; overflow: hidden; white-space: nowrap;">
                                            <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i
                                                class="fa fa-star"></i><i class="fa fa-star"></i>
                                        </span>
                                    </span>
                                    <span itemprop="rating" class="font-medium">{{ number_format($ratingFloat, 1) }}</span>
                                    <span class="text-gray-400">/5</span>
                                    @if ($entity->reviewCount)
                                        <span itemprop="count"
                                            class="text-gray-500">({{ number_format($entity->reviewCount) }})</span>
                                    @endif
                                </div>
                                <div class="price-wraper inline-flex items-center gap-2">
                                    <span class="text-gray-300">|</span>
                                    @if ($entity->hasVariants)
                                        @php
                                            $currentPrice = $defaultVariant['price'] ?? $entity->price;
                                            $struckRef = $defaultVariant['regular_price'] ?? $basePriceForDiscount;
                                            $showStruck =
                                                $struckRef > 0 && $currentPrice > 0 && $currentPrice < $struckRef;
                                        @endphp
                                        <b class="text-gray-500 line-through" id="price-product-old"
                                            style="{{ $showStruck ? '' : 'display:none;' }}">
                                            {{ money($struckRef) }}
                                        </b>
                                        <b class="text-danger" id="price-product">
                                            {{ $currentPrice > 0 ? money($currentPrice) : getModuleConfig('product.text_contact') }}
                                        </b>
                                    @elseif ($special && $special->pricePromotion > 0 && $special->pricePromotion < $entity->price)
                                        <b class="text-gray-500 line-through">
                                            {{ $special->priceRegularLabel }}
                                        </b>
                                        <b class="text-danger" id="price-product">
                                            {{ $special->pricePromotionLabel }}
                                        </b>
                                    @elseif ($entity->price > 0)
                                        <b class="text-danger" id="price-product">{{ $entity->priceLabel }}</b>
                                    @else
                                        <b class="text-gray-500">{{ getModuleConfig('product.text_contact') }}</b>
                                    @endif
                                    @if ($entity->weight && $entity->weight > 0)
                                        <span class="text-gray-500">
                                            /{{ (int) $entity->weight . $entity->weightUnit }}
                                        </span>
                                    @endif
                                </div>
                                @if (!empty($rewardEarn) && $rewardEarn > 0)
                                    <div class="mt-1 text-sm text-yellow-600">
                                        <i class="fi-rs-star"></i>
                                        Mua sản phẩm này nhận {{ number_format($rewardEarn, 0, '', ',') }} điểm thưởng
                                    </div>
                                @endif
                            </div>
                            <div class="description mb-30">
                                @if ($entity->manufacturer)
                                    <p>
                                        <i class="fa fa-chevron-down"></i> <b>Hãng sản xuất:</b>
                                        <a href="{!! $entity->manufacturer?->url !!}" title="{{ $entity->manufacturer?->name }}">
                                            <span>{{ $entity->manufacturer?->name }}</span>
                                        </a>
                                    </p>
                                @endif
                                @if (count($entity->categories))
                                    <p>
                                        <i class="fa fa-chevron-down"></i> <b>Loại sản phẩm:</b>
                                        @foreach ($entity->categories as $cat)
                                            <a
                                                href="{{ $cat->url }}"><span>{{ $cat->title }}</span></a>{{ !$loop->last ? ', ' : '' }}
                                        @endforeach
                                    </p>
                                @endif
                            </div>
                            @if (count($productOptions))
                                <div class="product-info product-option mb-50">
                                    @foreach ($productOptions as $key => $option)
                                        @include('web::product.structure._option')
                                    @endforeach
                                </div>
                            @endif
                            <div id="product-quantity"></div>
                            <div class="detail-extralink product-quantity mb-50 flex flex-wrap items-center gap-2">
                                <input type="hidden" name="product_id" value="{{ $entity->id }}">
                                <input type="number" name="quantity" class="detail-qty border rounded w-20 h-10 px-2"
                                    size="2" value="1" min="1">
                                @if (getConfigDb('config_stock_checkout'))
                                    @if ($entity->isCustom)
                                        <button id="consult-sign" type="button"
                                            class="button btn-secondary button-add-to-cart"
                                            data-url="{{ route('checkout.consultSign') }}">
                                            <i class="fas fa-adjust"></i>&nbsp;Tư vấn ngay
                                        </button>
                                    @endif
                                    @if ($entity->isAddCart)
                                        @if ($entity->inStock)
                                            <button id="button-cart" type="button"
                                                class="button btn-brand button-add-to-cart"
                                                data-url="{{ route('checkout.addToCart') }}">
                                                <i class="fa fa-shopping-cart"></i>&nbsp;Mua hàng
                                            </button>
                                        @else
                                            <button id="button-contact" type="button"
                                                class="button btn-secondary button-add-to-cart"
                                                @click="contactOpen = true">
                                                <i class="far fa-address-card"></i>&nbsp;Liên hệ mua hàng
                                            </button>
                                        @endif
                                    @endif
                                @else
                                    <button id="button-cart" type="button" class="button btn-brand button-add-to-cart"
                                        data-url="{{ route('checkout.addToCart') }}">
                                        <i class="fa fa-shopping-cart"></i>&nbsp;Mua hàng
                                    </button>
                                @endif
                                @foreach ($entity->linkSaleCustom as $item)
                                    @if (filled($item['name'] ?? null))
                                        <a href="{{ $item['link'] ?? '#' }}" class="button btn-brand button-add-to-cart"
                                            target="_blank" rel="nofollow">
                                            {!! $item['name'] !!}
                                        </a>
                                    @endif
                                @endforeach
                                <div class="action">
                                    <div class="wishlist @if ($userWishlist) active @endif" id="wishlist">
                                        <button type="button" class="product-icon fa fa-heart wishlist-61"
                                            title="Sản phẩm ưu thích" style="border-color: #F4a883;"
                                            onclick="userWishlist('{{ $entity->id }}');"></button>
                                    </div>
                                </div>
                            </div>
                            <div class="short-desc mb-30 font-lg">
                                {!! $entity->description !!}
                            </div>
                        </div>
                    </div>
                </div>
                @if (count($storeReviews) && $entity->isCustom)
                    <div class="store-review mt-16">
                        <div class="flex justify-center">
                            <h2 class="section-title style-2 mb-30">Sản phẩm đã làm</h2>
                        </div>
                        <div class="w-full">
                            <div class="carausel-5-columns-cover arrow-center relative">
                                <div class="slider-arrow slider-arrow-2 carausel-5-columns-arrow"
                                    id="carausel-5-columns-arrows"></div>
                                <div class="carausel-5-columns" id="carausel-5-columns">
                                    @foreach ($storeReviews as $review)
                                        <div class="card-1">
                                            <figure class="img-hover-scale overflow-hidden">
                                                <a href="{!! $review->url !!}" title="{!! $review->name !!}">
                                                    <img src="{!! $review->thumbnail(312, 340) !!}" alt="{!! $review->name !!}">
                                                    <div class="author-review">
                                                        <i class="fab fa-{{ $review->socialIcon }}"></i>
                                                        <span>by {!! $review->name !!}</span>
                                                    </div>
                                                </a>
                                            </figure>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="product-info mt-50">
                    <div class="tab-style3">
                        <ul class="nav nav-tabs uppercase flex justify-center list-none p-0 m-0">
                            <li class="nav-item">
                                <a class="nav-link cursor-pointer" :class="tab === 'description' ? 'active' : ''"
                                    id="description-tab" @click.prevent="tab = 'description'">Mô tả</a>
                            </li>
                            @if ($entity->isReview)
                                <li class="nav-item">
                                    <a class="nav-link cursor-pointer" :class="tab === 'reviews' ? 'active' : ''"
                                        id="reviews-tab" @click.prevent="tab = 'reviews'">
                                        Đánh giá ({!! $entity->reviewCount ?? 0 !!})
                                    </a>
                                </li>
                            @endif
                        </ul>
                        <div class="tab-content shop_info_tab entry-main-content">
                            <div class="tab-pane" id="description"
                                :class="tab === 'description' ? 'fade show active' : 'hidden'">
                                <div class="inner">
                                    <div class="product-description">
                                        <div>{!! $entity->content() !!}</div>
                                        <div class="gradient"></div>
                                    </div>
                                    <div class="wrap-btn-more pt-4 pb-4">
                                        <div class="flex justify-center">
                                            <a class="btn btn-more btn--view-more-desc">
                                                <span class="more-text">Xem thêm <i class="fa fa-chevron-down"></i></span>
                                                <span class="less-text">Thu gọn <i class="fa fa-chevron-up"></i></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if ($entity->isReview)
                                <div class="tab-pane" id="reviews"
                                    :class="tab === 'reviews' ? 'fade show active' : 'hidden'">
                                    <div class="comments-area">
                                        <div class="w-full">
                                            <h4 class="mb-30">Đánh giá của khách hàng</h4>
                                        </div>
                                    </div>
                                    @includeIf('web::product.structure.comment')
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if (count($relatedProducts))
                    <div class="mt-16">
                        <div class="flex justify-center">
                            <h2 class="section-title style-2 mb-30">Có thể bạn muốn xem?</h2>
                        </div>
                        <div class="related-products grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach ($relatedProducts as $item)
                                @include('web::product.structure._product', ['product' => $item])
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (count($latestBlogs))
                    <div class="mt-16">
                        <div class="flex justify-center">
                            <h2 class="section-title style-2 mb-30">Chia sẻ</h2>
                        </div>
                        <div class="blog-latest grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            @foreach ($latestBlogs as $blog)
                                <article class="text-center wow fadeIn animated hover-up mb-30">
                                    <div class="post-thumb">
                                        <a href="{!! $blog->url !!}" title="{!! $blog->title !!}">
                                            <img src="{!! $blog->thumbnail(635, 420) !!}" alt="{!! $blog->title !!}"
                                                class="border-radius-15">
                                        </a>
                                    </div>
                                    <div class="entry-content-2">
                                        <h4 class="post-title mb-15 font-md">
                                            <a href="{!! $blog->url !!}" title="{!! $blog->title !!}">
                                                {!! $blog->title !!}
                                            </a>
                                        </h4>
                                        <div class="entry-meta font-xs color-grey mt-10 pb-10">
                                            <div>
                                                <span class="post-on mr-10">{!! $blog->publishedDate !!}</span>
                                                <span class="hit-count has-dot mr-10">{!! $blog->viewed !!} lượt
                                                    xem</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div x-show="contactOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    x-transition>
                    <div class="absolute inset-0 bg-black/50" @click="contactOpen = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                        <div class="p-6">
                            <h4 class="font-semibold text-lg mb-3">Liên hệ mua hàng:</h4>
                            <ul class="list-disc pl-5 space-y-1 text-sm">
                                <li>Số điện thoại: {{ getConfigDb('config_telephone') }}</li>
                                <li>Email: {{ getConfigDb('config_email') }}</li>
                                <li>Facebook:
                                    <a href="{{ getConfigDb('config_facebook') }}" target="_blank" rel="nofollow"
                                        class="text-brand underline">
                                        {{ getConfigDb('config_name') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="px-6 py-3 border-t border-gray-200 flex justify-end">
                            <button type="button" class="btn btn-sm" @click="contactOpen = false">Đồng ý</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
