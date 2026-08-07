@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{{ getConfigDb('config_name') }}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{{ getConfigDb('config_facebook') }}" />
    <meta property="og:url" itemprop="url" content="{{ route('home') }}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{{ $titleSeo }}" itemprop="headline" property="og:title" />
    <meta content="{{ $descriptionSeo }}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:url" content="{{ route('home') }}" />
    <meta name="twitter:title" content="{{ $titleSeo }}" />
    <meta name="twitter:description" content="{{ $descriptionSeo }}" />
    <meta name="twitter:image" content="" />
    <meta name="twitter:site" content="{{ getConfigDb('config_name') }}" />
    <meta name="twitter:creator" content="{{ getConfigDb('config_name') }}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{{ getConfigDb('config_name') }}","url":"{{ getConfigDb('config_domain') }}"}
    </script>
@endsection
@include('web::page.child.banner')
@section('content')
    <div class="home-page infunstudio-package pd-infunstudio wrap-content">
        <div class="w-full">
            @include('web::page.child.about_us')
            @include('web::page.child.store_review')
            @include('web::page.child.product_feature')
            @include('web::page.child.blog_latest')
        </div>
    </div>
@endsection
