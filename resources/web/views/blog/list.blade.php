@php
    $image = $createdAt = $updatedAt = $createdAt = '';
    if (count($entities)) {
        $image = $entities[0]->thumbnail(800, 354);
        $createdAt = $entities[0]->publishedDate;
        $updatedAt = $entities[0]->modifiedDate;
    }
@endphp
@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! route('blog.getList') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $image !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $createdAt !!}" itemprop="datePublished" name="pubdate" />
    <meta content="{!! $updatedAt !!}" itemprop="dateModified" name="lastmod" />
    <meta content="{!! $createdAt !!}" itemprop="dateCreated" />
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! route('blog.getList') !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $image !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    @foreach ($entities as $item)
        <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"NewsArticle","mainEntityOfPage":{"@@type":"WebPage","@@id":"{!! $item->url !!}"},"headline":"{!! $item->metaTitle !!}","description":"{!! $item->metaDescription !!}","image":{"@@type":"ImageObject","url":"{!! $item->thumbnail(900, 540) !!}","width":900,"height":540},"datePublished":"{!! $item->publishedDate !!}","dateModified":"{!! $item->modifiedDate !!}","author":{"@@type":"Organization","name":"{!! getConfigDb('config_name') !!}"},"publisher":{"@@type":"Organization","name":"{!! getConfigDb('config_name') !!}","logo":{"@@type":"ImageObject","url":"{!! thumbnail(getConfigDb('config_logo')) !!}","width":180,"height":55 }},"about": "{!! $titleSeo !!}"}
    </script>
    @endforeach
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! $titleSeo !!}","alternateName":"{!! $descriptionSeo !!}","url":"{!! request()->url() !!}"}
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb', [
        'titlePage' => isset($title) ? $title : 'Tin tức',
    ])
    <div class="container-xl mb-30">
        <div class="row">
            <div class="col-lg-9">
                <div class="shop-product-fillter mb-50 pr-30">
                    <div class="totall-product">
                        <p>Có <strong class="text-brand">{{ $entities->total() }}</strong> bài viết!</p>
                    </div>
                    @include('web::blog.structure._sort_by')
                </div>
                <div class="loop-grid pr-30">
                    <div class="row">
                        @foreach ($entities as $item)
                            @include('web::blog.structure._blog')
                        @endforeach
                    </div>
                </div>
                <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('web::share.structure._paging', [
                            'removeKey' => ['category_id_eq', 'per_page'],
                        ]) !!}
                    </nav>
                </div>
            </div>
            @include('web::blog.structure._side_bar')
        </div>
    </div>
@endsection
