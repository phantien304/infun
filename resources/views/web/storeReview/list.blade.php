@php
    $image = $createdAt = $updatedAt = $createdAt = '';
    if (count($entities)) {
        $image = $entities[0]->getImageClient(800, 354);
        $createdAt = $entities[0]->created_at;
        $updatedAt = $entities[0]->updated_at;
    }
@endphp
@extends('web.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! routeArea('storeReview.getList') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $image !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $createdAt !!}" itemprop="datePublished" name="pubdate" />
    <meta content="{!! $updatedAt !!}" itemprop="dateModified" name="lastmod" />
    <meta content="{!! $createdAt !!}" itemprop="dateCreated" />
    @include('web.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:url" content="{!! routeArea('storeReview.getList') !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $image !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {
            "@@context":"http://schema.org",
            "@@type":"BreadcrumbList",
            "itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}
        }
    </script>
    @if ($entities->isNotEmpty())
        <script type="application/ld+json">
            {
                "@@context":"http://schema.org",
                "@@graph": [
                @foreach ($entities as $index => $item)
                    {
                        "@@type": "NewsArticle",
                        "mainEntityOfPage": {
                            "@@type": "WebPage",
                            "@@id": "{{ $item->getUrlClient() }}"
                        },
                        "headline": {!! json_encode($item->getMetaTitle(), JSON_UNESCAPED_UNICODE) !!},
                        "description": {!! json_encode($item->getMetaDescription(), JSON_UNESCAPED_UNICODE) !!},
                        "image": {
                            "@@type": "ImageObject",
                            "url": "{{ $item->getImageClient(900, 540) }}",
                            "width": 900,
                            "height": 540
                        },
                        "datePublished": "{{ $item->created_at }}",
                        "dateModified": "{{ $item->updated_at }}",
                        "author": {
                            "@@type": "Organization",
                            "name": {!! json_encode(getConfigDb('config_name'), JSON_UNESCAPED_UNICODE) !!}
                        },
                        "publisher": {
                            "@@type": "Organization",
                            "name": {!! json_encode(getConfigDb('config_name'), JSON_UNESCAPED_UNICODE) !!},
                            "logo": {
                                "@@type": "ImageObject",
                                "url": "{{ asset(getConfigDb('config_logo')) }}",
                                "width": 180,
                                "height": 55
                            }
                        },
                        "about": {!! json_encode($titleSeo, JSON_UNESCAPED_UNICODE) !!}
                    }{{ $index < $entities->count() - 1 ? ',' : '' }}
                @endforeach
            ]
            }
        </script>
    @endif
    <script type="application/ld+json">
        {
            "@@context": "http://schema.org",
            "@@type": "WebSite",
            "name": {!! json_encode($titleSeo, JSON_UNESCAPED_UNICODE) !!},
            "alternateName": {!! json_encode($descriptionSeo, JSON_UNESCAPED_UNICODE) !!},
            "url": "{{ request()->url() }}"
        }
    </script>
@stop
@section('content')
    @include('web.share.structure._breadcrumb', [
        'titlePage' => isset($title) ? $title : 'Khách hàng đánh giá',
    ])
    <div class="container-xl mb-30">
        <div class="row">
            <div class="col-lg-9">
                <div class="shop-product-fillter mb-50 pr-30">
                    <div class="totall-product">
                        <p>Có <strong class="text-brand">{{ $entities->total() }}</strong> đánh giá!</p>
                    </div>
                    @include('web.storeReview.structure._sort_by')
                </div>
                <div class="loop-grid pr-30">
                    <div class="row">
                        @foreach ($entities as $item)
                            @include('web.storeReview.structure._storeReview')
                        @endforeach
                    </div>
                </div>
                <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('web.share.structure._paging', [
                            'removeKey' => ['category_id_eq', 'per_page'],
                        ]) !!}
                    </nav>
                </div>
            </div>
            @include('web.storeReview.structure._side_bar')
        </div>
    </div>
@endsection
