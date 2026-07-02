@extends('web::layouts.main')
@section('meta')
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}"/>
    <meta property="og:type" content="article"/>
    <meta property="og:url" itemprop="url" content="{!! route('tags.getList') !!}"/>
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title"/>
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description"/>
    @include('web::share.structure._meta_common')
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb', ['titlePage' => 'Tags'])
    <div class="container-xl mt-30 mb-50">
        <div class="single-header style-2 mb-3">
            <h2>Tất cả Tags</h2>
        </div>
        <ul class="tags-list list-unstyled d-flex flex-wrap">
            @foreach ($entities as $item)
                <li class="m-2">
                    <a href="{{ $item->url }}" title="{{ $item->title }}" class="btn btn-sm btn-light hover-up">
                        <i class="fi-rs-cross mr-10"></i>{{ $item->title }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@stop
