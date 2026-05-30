@php
    /** @var \App\Data\Output\ProductDTO $product */
    /** @var \App\Models\Entities\Product $entity */
    $special = $product->productSpecial;
    $priceFinal = $special?->pricePromotion ?: $product->price;
@endphp
@section('script_header')
    <script type="text/javascript">
        var options = {!! json_encode($options) !!};
        var urlUserWishlist = '{{ route('account.userWishlist') }}';
        var priceProduct = {{ $priceFinal }};
        var urlListReview = '{!! route('product.getListReview', ['product_id' => $product->id, 'pageIndex' => 1]) !!}';
    </script>
@stop
@extends('web.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! $product->url !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $product->thumbnail(800, 354, 'client') !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $product->dateAvailable !!}" itemprop="datePublished" name="pubdate" />
    <meta content="{!! $product->modifiedDate !!}" itemprop="dateModified" name="lastmod" />
    <meta content="{!! $product->publishedDate !!}" itemprop="dateCreated" />
    @include('web.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! $product->url !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $product->thumbnail(800, 354, 'client') !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@context":"http://schema.org","@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@context":"https://schema.org/","@type":"Product","url":"{!! $product->url !!}","image":"{!! $product->thumbnail(540, 540, 'client') !!}","name":"{!! $titleSeo !!}","description":"{!! $descriptionSeo !!}","sku":"{!! $product->sku !!}","aggregateRating":{"@type":"AggregateRating","ratingValue":"{!! $product->rating !!}","reviewCount":"{!! $product->totalRating !!}"},"brand":{"@type":"Brand","name":"{{ $product->manufacturer?->name ?? getConfigDb('config_name') }}"},"offers":{"@type":"Offer","url":"{!! $product->url !!}","seller":{"@type":"Organization","name":"{{ getConfigDb('config_name') }}","url":"{{ route('home') }}","telephone":"{{ getConfigDb('config_telephone') }}","email":"{{ getConfigDb('config_email') }}","address":"{{ getConfigDb('config_address') }}"},"itemCondition":"https://schema.org/NewCondition","availability":"https://schema.org/InStock","priceValidUntil":"{!! $product->dateAvailable !!}","priceCurrency":"VND","price":{!! $priceFinal !!}}}
    </script>
    <script type="application/ld+json">
        {"@context":"http://schema.org","@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
    </script>
@stop
@section('content')
    @include('web.share.structure._breadcrumb_v2')
    <div class="container-xl mb-30">
        <div class="col-lg-12 m-auto">
            <div class="product-detail accordion-detail">
                <div class="row mb-50 mt-30">
                    <div class="col-md-6 col-sm-12 col-xs-12 mb-30">
                        @include('web.product.structure.image')
                    </div>
                    <div class="col-md-6 col-sm-12 col-xs-12 mb-md-0 mb-sm-5">
                        <div class="detail-info pr-30 pl-30">
                            @if ($special && $special->discountPercent)
                                <span class="stock-status out-stock">Tiết kiệm -{{ $special->discountPercent }}%</span>
                            @endif
                            <h1>{{ $product->name }}</h1>
                            <div class="d-flex mt-2 mb-20" style="font-size: 20px;">
                                <div itemtype="http://data-vocabulary.org/Review-aggregate" itemscope=""
                                    itemprop="review" style="display: block;">
                                    <img src="/client/images/stars-{!! $product->ratingRounded !!}.png"
                                        style="height: 25px; vertical-align: top;"
                                        alt="{!! $product->totalRating !!} đánh giá" />&nbsp;
                                    <span itemprop="rating">{!! $product->ratingRounded !!}</span>/5&nbsp;
                                    @if ($product->totalRating)
                                        <span itemprop="count">({!! $product->totalRating !!})</span>
                                    @endif
                                </div>
                                <div class="price-wraper">
                                    &nbsp;|&nbsp;
                                    @if ($special && $special->pricePromotion > 0)
                                        <b class="text-secondary text-decoration-line-through">{{ $special->priceRegularLabel }}</b>
                                        &nbsp;<b class="text-danger" id="price-product">{{ $special->pricePromotionLabel }}</b>
                                    @elseif ($product->price > 0)
                                        <b class="text-danger" id="price-product">{{ $product->priceLabel }}</b>
                                    @else
                                        <b class="text-secondary">{{ getModuleConfig('product.text_contact') }}</b>
                                    @endif
                                    @if ($product->weight && $product->weight > 0)
                                        <span class="text-secondary">/{{ (int) $product->weight . $product->weightUnit }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="description mb-30">
                                @if ($product->manufacturer)
                                    <p>
                                        <i class="fa fa-chevron-down"></i> <b>Hãng sản xuất:</b>
                                        <a href="{!! $product->manufacturer->url !!}"
                                            title="{{ $product->manufacturer->name }}">
                                            <span>{{ $product->manufacturer->name }}</span>
                                        </a>
                                    </p>
                                @endif
                                @if (count($product->categories))
                                    <p>
                                        <i class="fa fa-chevron-down"></i> <b>Loại sản phẩm:</b>
                                        @foreach ($product->categories as $cat)
                                            <a href="{{ $cat->url }}"><span>{{ $cat->title }}</span></a>{{ ! $loop->last ? ', ' : '' }}
                                        @endforeach
                                    </p>
                                @endif
                            </div>
                            @if (count($options))
                                <div class="product-info product-option mb-50">
                                    @foreach ($options as $key => $option)
                                        @include('web.product.structure._option')
                                    @endforeach
                                </div>
                            @endif
                            <div id="product-quantity"></div>
                            <div class="detail-extralink product-quantity mb-50">
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="number" name="quantity" class="detail-qty border radius" size="2"
                                    value="1" min="1" style="height: 42px">
                                @if (getConfigDb('config_stock_checkout'))
                                    @if ($product->isCustom)
                                        <button id="consult-sign" class="button btn-secondary button-add-to-cart mt-2 me-2">
                                            <i class="fas fa-adjust"></i>&nbsp;Tư vấn ngay
                                        </button>
                                    @endif
                                    @if ($product->isAddCart)
                                        @if ($product->quantity > 0)
                                            <button id="button-cart" class="button btn-brand button-add-to-cart mt-2 me-2">
                                                <i class="fa fa-shopping-cart"></i>&nbsp;Mua hàng
                                            </button>
                                        @else
                                            <button id="button-contact"
                                                class="button btn-secondary button-add-to-cart mt-2 me-2"
                                                data-bs-toggle="modal" data-bs-target="#alertContactOrder">
                                                <i class="far fa-address-card"></i>&nbsp;Liên hệ mua hàng
                                            </button>
                                        @endif
                                    @endif
                                @else
                                    <button id="button-cart" class="button btn-brand button-add-to-cart mt-2 me-2">
                                        <i class="fa fa-shopping-cart"></i>&nbsp;Mua hàng
                                    </button>
                                @endif
                                @foreach ($product->linkSaleCustom as $item)
                                    @if (filled($item['name'] ?? null))
                                        <a href="{{ $item['link'] ?? '#' }}"
                                            class="button btn-brand button-add-to-cart mt-2 me-2" target="_blank"
                                            rel="nofollow">
                                            {!! $item['name'] !!}
                                        </a>
                                    @endif
                                @endforeach
                                <div class="action pull-left mt-2">
                                    <div class="pull-left">
                                        <div class="wishlist @if ($wishlist) active @endif" id="wishlist">
                                            <button class="product-icon fa fa-heart product-icon wishlist-61"
                                                title="Sản phẩm ưu thích" style="border-color: #F4a883;"
                                                onclick="userWishlist('{{ $product->id }}');"></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="short-desc mb-30 font-lg">
                                {!! $product->description !!}
                            </div>
                        </div>
                    </div>
                </div>
                @if (count($storeReviews) && $product->isCustom)
                    <div class="row store-review mt-60">
                        <div class="col-12 d-flex justify-content-center">
                            <h2 class="section-title style-2 mb-30">Sản phẩm đã làm</h2>
                        </div>
                        <div class="col-12">
                            <div class="carausel-5-columns-cover arrow-center position-relative">
                                <div class="slider-arrow slider-arrow-2 carausel-5-columns-arrow"
                                    id="carausel-5-columns-arrows"></div>
                                <div class="carausel-5-columns" id="carausel-5-columns">
                                    @foreach ($storeReviews as $review)
                                        <div class="card-1">
                                            <figure class="img-hover-scale overflow-hidden">
                                                <a href="{!! $review->url !!}" title="{!! $review->name !!}">
                                                    <img src="{!! $review->thumbnail(312, 340, 'client') !!}"
                                                        alt="{!! $review->name !!}">
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
                        <ul class="nav nav-tabs text-uppercase justify-content-center">
                            <li class="nav-item">
                                <a class="nav-link active" id="description-tab" data-bs-toggle="tab"
                                    href="#description">Mô tả</a>
                            </li>
                            @if ($product->isReview)
                                <li class="nav-item">
                                    <a class="nav-link" id="reviews-tab" data-bs-toggle="tab" href="#reviews">
                                        Đánh giá ({!! $product->totalRating ?? 0 !!})
                                    </a>
                                </li>
                            @endif
                        </ul>
                        <div class="tab-content shop_info_tab entry-main-content">
                            <div class="tab-pane fade show active" id="description">
                                <div class="inner">
                                    <div class="product-description">
                                        <div>{!! $product->content !!}</div>
                                        <div class="gradient"></div>
                                    </div>
                                    <div class="wrap-btn-more pt-4 pb-4">
                                        <div class="d-flex justify-content-center">
                                            <a class="btn btn-more btn--view-more-desc">
                                                <span class="more-text">Xem thêm <i class="fa fa-chevron-down"></i></span>
                                                <span class="less-text">Thu gọn <i class="fa fa-chevron-up"></i></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if ($product->isReview)
                                <div class="tab-pane fade" id="reviews">
                                    <div class="comments-area">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mb-30">Đánh giá của khách hàng</h4>
                                                <div id="review" class="comment-list"
                                                    style="font-family: arial;font-size: 14px;line-height: 20px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @includeIf('web.product.structure.comment')
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @if (count($related))
                    <div class="row mt-60">
                        <div class="col-12 d-flex justify-content-center">
                            <h2 class="section-title style-2 mb-30">Có thể bạn muốn xem?</h2>
                        </div>
                        <div class="col-12">
                            <div class="row related-products justify-content-center">
                                @foreach ($related as $item)
                                    @include('web.product.structure._product', ['product' => $item])
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                @if (count($blogs))
                    <div class="row mt-60">
                        <div class="col-12 d-flex justify-content-center">
                            <h2 class="section-title style-2 mb-30">Chia sẻ</h2>
                        </div>
                        <div class="col-12">
                            <div class="row blog-latest">
                                @foreach ($blogs as $blog)
                                    <article class="col-xl-3 col-lg-4 col-md-6 text-center wow fadeIn animated hover-up mb-30 animated">
                                        <div class="post-thumb">
                                            <a href="{!! $blog->url !!}" title="{!! $blog->title !!}">
                                                <img src="{!! $blog->thumbnail(400, 250, 'client') !!}"
                                                    alt="{!! $blog->title !!}" class="border-radius-15">
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
                                                    <span class="hit-count has-dot mr-10">{!! $blog->viewed !!} lượt xem</span>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                <div class="modal fade" id="alertContactOrder" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-body">
                                <h4>Liên hệ mua hàng:</h4>
                                <ul>
                                    <li>Số điện thoại: {{ getConfigDb('config_telephone') }}</li>
                                    <li>Email: {{ getConfigDb('config_email') }}</li>
                                    <li>Facebook:
                                        <a href="{{ getConfigDb('config_facebook') }}" target="_blank" rel="nofollow">
                                            {{ getConfigDb('config_name') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đồng ý</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
