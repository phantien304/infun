@php
    $linkSale = $entity->link_sale;
    $linkSaleCustom = json_decode(array_get($entity, 'link_sale_custom', '[]'), true);
    $linkSale = preg_split('/\r|\n/', $linkSale);
    $titleProduct = $descriptionProduct = $contentProduct = $tagProduct = $urlProduct = '';
    if(isset($entity->productDescription)){
        $titleProduct = $entity->productDescription->name;
        $descriptionProduct = $entity->productDescription->description;
        $contentProduct = $entity->productDescription->content;
        $tagProduct = $entity->productDescription->tag;
        $urlProduct = $entity->productDescription->getUrlClient();
    }
    $priceSpecial = '';
    $discount = '';
    if(count($entity->productSpecials)){
        $productSpecial = $entity->productSpecials->sortByDesc('priority')->first();
        $priceSpecial = $productSpecial->price;
        $discount = round((($entity->price - $priceSpecial)/$entity->price)*100);
    }
@endphp
@section('script_header')
    <script type="text/javascript">
        var options = {!! json_encode($options) !!};
        var urlUserWishlist = '{{ route('account.userWishlist') }}';
        var priceProduct = @if(filled($priceSpecial)){{ $priceSpecial }} @else {{ $entity->price }} @endif;
        var urlListReview = '{!! route('product.getListReview', ['product_id' =>$entity->id, 'pageIndex' => 1 ]) !!}';
    </script>
@stop
@extends('client.infunstudio.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}"/>
    <meta property="og:rich_attachment" content="true"/>
    <meta property="og:type" content="article"/>
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}"/>
    <meta property="og:url" itemprop="url" content="{!! $urlProduct !!}"/>
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $entity->getImageClient(800, 354) !!}"/>
    <meta property="og:image:width" content="800"/>
    <meta property="og:image:height" content="354"/>
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title"/>
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description"/>
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $entity->date_available !!}" itemprop="datePublished" name="pubdate"/>
    <meta content="{!! $entity->updated_at !!}" itemprop="dateModified" name="lastmod"/>
    <meta content="{!! $entity->created_at !!}" itemprop="dateCreated"/>
    @include('client.infunstudio.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary"/>
    <meta name="twitter:url" content="{!! $urlProduct !!}"/>
    <meta name="twitter:title" content="{!! $titleSeo !!}"/>
    <meta name="twitter:description" content="{!! $descriptionSeo !!}"/>
    <meta name="twitter:image" content="{!! $entity->getImageClient(800, 354) !!}"/>
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}"/>
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}"/>
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@context":"http://schema.org","@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@context":"https://schema.org/","@type":"Product","url":"{!! $urlProduct !!}","image":"{!! $entity->getImageClient(540, 540) !!}","name":"{!! $titleSeo !!}","description":"{!! $descriptionSeo !!}","sku":"{!! $entity->sku !!}","aggregateRating":{"@type":"AggregateRating","ratingValue":"{!! $entity->rating !!}","reviewCount":"{!! $entity->total_rating !!}"},"brand":{"@type":"Brand","name":"{{ isset($entity->manufacturer) ? $entity->manufacturer->name : getConfigDb('config_name') }}"},"offers":{"@type":"Offer","url":"{!! $urlProduct !!}","seller":{"@type": "Organization","name": "{{ getConfigDb('config_name') }}","url": "{{ route('home') }}","telephone": "{{ getConfigDb('config_telephone') }}","email": "{{ getConfigDb('config_email') }}","address":"{{ getConfigDb('config_address') }}"},"itemCondition":"https://schema.org/NewCondition","availability":"https://schema.org/InStock","priceValidUntil":"{!! $entity->date_available !!}","priceCurrency":"VND","price":{!! $entity->price !!}}}
    </script>
    <script type="application/ld+json">
        {"@context":"http://schema.org","@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
    </script>
@stop
@section('content')
    @include('client.infunstudio.share.structure._breadcrumb_v2')
    <div class="container-xl mb-30">
        <div class="col-lg-12 m-auto">
            <div class="product-detail accordion-detail">
                <div class="row mb-50 mt-30">
                    <div class="col-md-6 col-sm-12 col-xs-12 mb-30">
                        @include('client.infunstudio.product.structure.image')
                    </div>
                    <div class="col-md-6 col-sm-12 col-xs-12 mb-md-0 mb-sm-5">
                        <div class="detail-info pr-30 pl-30">
                            @if($discount)
                                <span class="stock-status out-stock">Tiết kiệm -{{ $discount }}%</span>
                            @endif
                            <h1>{{ $titleProduct }}</h1>
                            <div class="d-flex mt-2 mb-20" style="font-size: 20px;">
                                <div itemtype="http://data-vocabulary.org/Review-aggregate" itemscope=""
                                     itemprop="review"
                                     style="display: block;">
                                    <img src="/client/images/stars-{!! $ratingReview !!}.png"
                                         style="height: 25px; vertical-align: top;"
                                         alt="{!! $entity->total_rating !!} đánh giá"/>&nbsp
                                    <span
                                        itemprop="rating">{!! $ratingReview !!}</span>/5&nbsp;@if($entity->total_rating)
                                        <span
                                            itemprop="count">({!! $entity->total_rating !!})</span>@endif
                                </div>
                                <div class="price-wraper">
                                    &nbsp|&nbsp
                                    @php
                                        $unit = 'gram';
                                        if(isset($entity->weightClass->weightClassDescription)){
                                            $unit = $entity->weightClass->weightClassDescription->unit;
                                        }
                                    @endphp
                                    @if($priceSpecial > 0)
                                        <b class="text-secondary text-decoration-line-through">{{ number_format($entity->price, 0, '', ',') }}đ</b>
                                        &nbsp;<b class="text-danger" id="price-product">{{ number_format($priceSpecial, 0, '', ',') }}đ</b>
                                    @elseif($entity->price > 0)
                                        <b class="text-danger" id="price-product">{{ number_format($entity->price, 0, '', ',') }}đ</b>
                                    @else
                                        <b class="text-secondary">Giá: Liên hệ</b>
                                    @endif
                                    @if($entity->weight > 0)
                                        <span class="text-secondary">/{{ intval($entity->weight).$unit }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="description mb-30">
                                @if(isset($entity->manufacturer))
                                    <p>
                                        <i class="fa fa-chevron-down"></i> <b>Hãng sản xuất:</b>
                                        <a href="{!! $entity->manufacturer->getUrlClient() !!}"
                                           title="{{ $entity->manufacturer->name }}">
                                            <span>{{ $entity->manufacturer->name }}</span>
                                        </a>
                                    </p>
                                @endif
                                @if(isset($entity->categories) && count($entity->categories))
                                    <p>
                                        <i class="fa fa-chevron-down"></i> <b>Loại sản phẩm:</b>
                                        <?php
                                        $listCategory = [];
                                        foreach ($entity->categories as $item) {
                                            $listCategory[] = '<a href="' . $item['slug'] . '"><span>' . $item['title'] . '</span></a>';
                                        }
                                        echo implode(', ', $listCategory);
                                        ?>
                                    </p>
                                @endif
                            </div>
                            @if(count($options))
                                <div class="product-info product-option mb-50">
                                    @foreach ($options as $key => $option)
                                        @include('client.infunstudio.product.structure._option')
                                    @endforeach
                                </div>
                            @endif
                            <div id="product-quantity"></div>
                            <div class="detail-extralink product-quantity mb-50">
                                <input type="hidden" name="product_id" value="{{ $entity->id }}">
                                <input type="number" name="quantity" class="detail-qty border radius"
                                       size="2" value="1" min="1" style="height: 42px">
                                @if(getConfigDb('config_stock_checkout'))
                                    @if($entity->is_custom)
                                        <button id="consult-sign"
                                                class="button btn-secondary button-add-to-cart mt-2 me-2">
                                            <i class="fas fa-adjust"></i>&nbsp;Tư vấn ngay
                                        </button>
                                    @endif
                                    @if($entity->is_add_cart)
                                        @if($entity->quantity > 0)
                                            <button id="button-cart"
                                                    class="button btn-brand button-add-to-cart mt-2 me-2">
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
                                @if(count($linkSaleCustom))
                                    @foreach($linkSaleCustom as $k => $item)
                                        @if(filled($item['name']))
                                            <a href="{{ $item['link'] }}"
                                               class="button btn-brand button-add-to-cart mt-2 me-2"
                                               target="_blank" rel="nofollow">
                                                {!! $item['name'] !!}
                                            </a>
                                        @endif
                                    @endforeach
                                @endif
                                <div class="action pull-left mt-2">
                                    <div class="pull-left">
                                        <div class="wishlist @if($wishlist) active @endif" id="wishlist">
                                            <button class="product-icon fa fa-heart product-icon wishlist-61"
                                                    title="Sản phẩm ưu thích" style="border-color: #F4a883;"
                                                    onclick="userWishlist('{{$entity->id}}');"></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="short-desc mb-30 font-lg">
                                {!! $descriptionProduct !!}
                            </div>
                        </div>
                    </div>
                </div>
                @if(count($storeReviews) && $entity->is_custom)
                    <div class="row store-review mt-60">
                        <div class="col-12 d-flex justify-content-center">
                            <h2 class="section-title style-2 mb-30">Sản phẩm đã làm</h2>
                        </div>
                        <div class="col-12">
                            <div class="carausel-5-columns-cover arrow-center position-relative">
                                <div class="slider-arrow slider-arrow-2 carausel-5-columns-arrow"
                                     id="carausel-5-columns-arrows"></div>
                                <div class="carausel-5-columns" id="carausel-5-columns">
                                    @foreach($storeReviews as $item)
                                        <div class="card-1">
                                            <figure class="img-hover-scale overflow-hidden">
                                                <a href="{!! $item->getUrlClient() !!}"
                                                   title="{!! $item->name !!}">
                                                    <img src="{!! $item->getImageClient(312, 340) !!}"
                                                         alt="{!! $item->name !!}">
                                                    <div class="author-review">
                                                        <i class="fab fa-{{ $item->social_icon }}"></i>
                                                        <span>by {!! $item->name !!}</span>
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
                            @if($entity->is_review)
                                <li class="nav-item">
                                    <a class="nav-link" id="reviews-tab" data-bs-toggle="tab" href="#reviews">Đánh giá
                                        ({!! $entity->total_rating ?? 0 !!})</a>
                                </li>
                            @endif
                            @if($entity->is_custom)
                                {{--<li class="nav-item">--}}
                                    {{--<a class="nav-link" id="order-guide-tab" data-bs-toggle="tab"--}}
                                       {{--href="#order-guide">Hướng dẫn đặt hàng</a>--}}
                                {{--</li>--}}
                            @endif
                        </ul>
                        <div class="tab-content shop_info_tab entry-main-content">
                            <div class="tab-pane fade show active" id="description">
                                <div class="inner">
                                    <div class="product-description">
                                        <div>{!! $contentProduct !!}</div>
                                        <div class="gradient"></div>
                                    </div>
                                    <div class="wrap-btn-more pt-4 pb-4">
                                        <div class="d-flex justify-content-center">
                                            <a class="btn btn-more btn--view-more-desc">
                                            <span class="more-text">Xem thêm
                                                <i class="fa fa-chevron-down"></i>
                                            </span>
                                                <span class="less-text">Thu gọn
                                                <i class="fa fa-chevron-up"></i>
                                            </span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if($entity->is_review)
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
                                    @include('client.infunstudio.product.structure.comment')
                                </div>
                            @endif
                            @if($entity->is_custom)
                                {{--<div class="tab-pane fade" id="order-guide">--}}
                                    {{--<div class="home-slide-cover">--}}
                                        {{--<div class="hero-slider-1 style-4 dot-style-1 dot-style-1-position-1">--}}
                                            {{--<div class="single-hero-slider single-animation-wrap"--}}
                                                 {{--style="background-image: url(/data/banner/2021-09-23/slider-1.png)">--}}
                                                {{--<div class="slider-content">--}}
                                                    {{--<h1 class="display-2 mb-40">--}}
                                                        {{--Pure Coffe<br/>--}}
                                                        {{--Big discount--}}
                                                    {{--</h1>--}}
                                                    {{--<p class="mb-65">Save up to 50% off on your first order</p>--}}
                                                {{--</div>--}}
                                            {{--</div>--}}
                                            {{--<div class="single-hero-slider single-animation-wrap"--}}
                                                 {{--style="background-image: url(/data/banner/2021-09-23/slider-2.png)">--}}
                                                {{--<div class="slider-content">--}}
                                                    {{--<h1 class="display-2 mb-40">--}}
                                                        {{--Snacks box<br/>--}}
                                                        {{--daily save--}}
                                                    {{--</h1>--}}
                                                    {{--<p class="mb-65">Sign up for the daily newsletter</p>--}}
                                                {{--</div>--}}
                                            {{--</div>--}}
                                        {{--</div>--}}
                                        {{--<div class="slider-arrow hero-slider-1-arrow"></div>--}}
                                    {{--</div>--}}
                                {{--</div>--}}
                            @endif
                        </div>
                    </div>
                </div>
                @if(count($related))
                    <div class="row mt-60">
                        <div class="col-12 d-flex justify-content-center">
                            <h2 class="section-title style-2 mb-30">Có thể bạn muốn xem?</h2>
                        </div>
                        <div class="col-12">
                            <div class="row related-products justify-content-center">
                                @foreach ($related as $product)
                                    @include('client.infunstudio.product.structure._related')
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                @if(count($blogs))
                    <div class="row mt-60">
                        <div class="col-12 d-flex justify-content-center">
                            <h2 class="section-title style-2 mb-30">Chia sẻ</h2>
                        </div>
                        <div class="col-12">
                            <div class="row blog-latest">
                                @foreach($blogs as $item)
                                    @if(!isset($item->blogDescription))
                                        @continue
                                    @endif
                                    @php
                                        $blogUrl = $item->blogDescription->getUrlClient();
                                        $blogTitle = $item->blogDescription->title;
                                    @endphp
                                    <article
                                        class="col-xl-3 col-lg-4 col-md-6 text-center wow fadeIn animated hover-up mb-30 animated">
                                        <div class="post-thumb">
                                            <a href="{!! $blogUrl !!}" title="{!! $blogTitle !!}">
                                                <img src="{!! $item->getImageClient() !!}" alt="{!! $blogTitle !!}"
                                                     class="border-radius-15">
                                            </a>
                                        </div>
                                        <div class="entry-content-2">
                                            <h4 class="post-title mb-15 font-md">
                                                <a href="{!! $blogUrl !!}" title="{!! $blogTitle !!}">
                                                    {!! $blogTitle !!}
                                                </a>
                                            </h4>
                                            <div class="entry-meta font-xs color-grey mt-10 pb-10">
                                                <div>
                                                    <span class="post-on mr-10">
                                                        {!! \Carbon\Carbon::parse($item->created_at)->format('d/m/Y') !!}
                                                    </span>
                                                    <span
                                                        class="hit-count has-dot mr-10">{!! $item->viewed !!} lượt xem</span>
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
                            <div class="modal-body"><h4>Liên hệ mua hàng:</h4>
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
