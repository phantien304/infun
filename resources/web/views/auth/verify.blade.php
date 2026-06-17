@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! route('auth.verifyEmail') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! route('auth.verifyEmail') !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! $titleSeo !!}","alternateName":"{!! $descriptionSeo !!}","url":"{!! route('auth.verifyEmail') !!}"}
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb_v2')
    <div class="page-content pt-150 pb-150">
        <div class="container-xl form-login">
            <div class="row">
                <div class="col-lg-10 col-md-12 m-auto">
                    <div class="row">
                        <div class="col-xl-12 mb-30">
                            @if (session()->has('success'))
                                <div class="alert alert-success">{!! session()->get('success') !!}</div>
                            @endif
                            @if (session()->has('failed'))
                                <div class="alert alert-danger">{!! session()->get('failed') !!}</div>
                            @endif
                        </div>
                        <div class="col-lg-5 col-md-8 m-auto">
                            <div class="card2 card border-0 px-4">
                                <h1 class="text-center mb-30">Chào bạn!</h1>
                                <div class="row mt-2 mb-30 text-center">
                                    @if ($success)
                                        <p class="text-success">Bạn đã xác thực tài khoản thành công</p>&nbsp;
                                    @else
                                        <p class="text-danger">Bạn đã xác thực tài khoản thất bại. Vui lòng kiểm tra lại
                                            liên kết.</p>
                                    @endif
                                </div>
                                <div class="text-center">
                                    @if ($success)
                                        <a href="{{ route('auth.login') }}" class="btn" title="Đăng nhập tài khoản">
                                            Đăng nhập tài khoản
                                        </a>
                                    @else
                                        <a href="{{ route('home') }}" class="btn" title="Tiếp tục mua hàng">
                                            Tiếp tục mua hàng
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
