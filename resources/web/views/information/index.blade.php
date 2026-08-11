@php
    $title = $entity->title;
    $descriptionInformation = $entity->description;
    $contentInformation = $entity->content();
    $urlInformation = $entity->url;
    $imageDefault = thumbnail(getModuleConfig('img_default'), 800, 354);
@endphp
@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! $urlInformation !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $imageDefault !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $entity->publishedDate !!}" itemprop="datePublished" name="pubdate" />
    <meta content="{!! $entity->modifiedDate !!}" itemprop="dateModified" name="lastmod" />
    <meta content="{!! $entity->publishedDate !!}" itemprop="dateCreated" />
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! $urlInformation !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $imageDefault !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"NewsArticle","mainEntityOfPage":{"@@type":"WebPage","@@id":"{!! $urlInformation !!}"},"headline":"{!! $titleSeo !!}","description":"{!! $descriptionSeo !!}","image":{"@@type":"ImageObject","url":"{!! $imageDefault !!}","width":900,"height":540},"datePublished":"{!! $entity->publishedDate !!}","dateModified":"{!! $entity->modifiedDate !!}","author":{"@@type":"Organization","name":"{!! getConfigDb('config_name') !!}"},"publisher":{"@@type":"Organization","name":"{!! getConfigDb('config_name') !!}","logo":{"@@type":"ImageObject","url":"{!! thumbnail(getConfigDb('config_logo')) !!}","width":180,"height":55 }},"about":"{!! $title !!}"}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb', ['titlePage' => $title])
    <div class="single-content pt-50">
        <div class="container-xl">
            <div class="row">
                <div class="col-lg-12 m-auto information-wrapper information-detail" id="information-info">
                    <header>
                        <h1 class="blog-title">
                            {!! $title !!}
                        </h1>
                    </header>
                    <section class="content">
                        <section class="description d-flex mt-2 pt-2">
                            {!! $descriptionInformation !!}
                        </section>
                        <section class="blog-content mt-2 pt-2">
                            <div class="content-wrap clearfix">
                                {!! $contentInformation !!}
                            </div>
                        </section>
                    </section>
                    <div class="comment-form">
                        <h4 class="mb-15">Bình luận</h4>
                        <div class="row">
                            <div class="fb-comments" data-href="{!! $urlInformation !!}"
                                 data-colorscheme="light" data-numposts="5" data-order-by="social"
                                 data-width="100%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
