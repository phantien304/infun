@extends('web::layouts.main')
@section('meta')
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}"/>
    <meta property="og:type" content="article"/>
    <meta property="og:url" itemprop="url" content="{!! route('home') !!}"/>
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}"/>
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title"/>
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description"/>
    @include('web::share.structure._meta_common')
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
@stop
@section('content')
    <div class="home-page wrap-content">
        <div class="container-xl p-5">
            <div class="page-content pt-150 pb-150">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-8 col-lg-10 col-md-12 m-auto text-center">
                            <h1 class="display-2 mb-30">Trang không tồn tại</h1>
                            <p class="font-lg text-grey-700 mb-30">
                                Liên kết bạn click có thể bị hỏng hoặc trang có thể đã bị xóa.<br>
                                Ghé thăm <a href="{!! route('home') !!}"><span>Trang chủ</span></a> hoặc
                                <a href="{!! route('contact.index') !!}"><span>Liên hệ chúng tôi</span></a> về sự cố bạn gặp.
                            </p>
                            <div class="header-style-1">
                                <div class="search-style-2">
                                    <form action="/san-pham" method="get" style="margin: 0 auto;">
                                        <input type="text" name="product_description[name_cons]" class="form-control"
                                               value="{{ data_get(request()->get('product_description'), 'name_cons') }}"
                                               placeholder="Bạn muốn tìm…" style="max-width: none;">
                                    </form>
                                </div>
                            </div>
                            <a class="btn btn-default submit-auto-width font-xs hover-up mt-30" href="{!! route('home') !!}">
                                <i class="fi-rs-home mr-5"></i> Về trang chủ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
