@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! route('auth.forgotPassword') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! route('auth.forgotPassword') !!}" />
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
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! $titleSeo !!}","alternateName":"{!! $descriptionSeo !!}","url":"{!! route('auth.forgotPassword') !!}"}
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
                        <div class="col-lg-5 m-auto col-md-8">
                            <div class="card2 card border-0 px-4">
                                <div class="heading_s1">
                                    <h1 class="mb-5">Đặt lại mật khẩu</h1>
                                    <p class="mb-30">Vui lòng nhập email để thay đổi mật khẩu</p>
                                </div>
                                <form action="{{ route('auth.forgotPassword') }}" method="POST">
                                    @csrf
                                    <div class="">
                                        <input type="email" name="email" placeholder="Nhập email"
                                            value="{{ old('email') }}"
                                            class="form-control @if ($errors->has('email')) is-invalid @endif">
                                        @if ($errors->has('email'))
                                            <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                                        @endif
                                    </div>
                                    <div class="d-grid gap-2 col-12 mx-auto text-center">
                                        <button type="submit" class="btn btn-md btn-block mt-4">Đặt lại mật khẩu
                                        </button>
                                    </div>
                                    <div class="d-grid gap-2 col-12 mx-auto text-center">
                                        <a href="{{ route('auth.login') }}" class="btn-link text-decoration-none mt-30"
                                            title="Quay lại đăng nhập">
                                            <i class="fi-rs-arrow-left mr-10 back"></i>Quay lại đăng nhập
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
