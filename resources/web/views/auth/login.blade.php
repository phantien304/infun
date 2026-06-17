@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! route('auth.login') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! thumbnail(getModuleConfig('img_default'), 800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! route('auth.login') !!}" />
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
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! $titleSeo !!}", "alternateName":"{!! $descriptionSeo !!}","url":"{!! route('auth.login') !!}"}
    </script>
@stop
@section('script_header')
    <script type="text/javascript">
        if (window.location.hash && window.location.hash === '#_=_') {
            window.location.hash = '';
        }
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
                        <div class="col-lg-6 pr-30 d-none d-lg-block">
                            <img class="border-radius-15" src="/data/banner/2021-12-17/dau_muc_2.png" alt="">
                        </div>
                        <div class="col-lg-6 col-md-8">
                            <div class="card2 card border-0 px-4">
                                <h1>Đăng nhập</h1>
                                <form action="{{ route('auth.login') }}" method="POST">
                                    @csrf
                                    <div class="mt-4">
                                        <input type="email" name="email" placeholder="Nhập email"
                                            value="{{ old('email') }}"
                                            class="form-control @if ($errors->has('email')) is-invalid @endif">
                                        @if ($errors->has('email'))
                                            <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                                        @endif
                                    </div>
                                    <div class="input-group mt-3">
                                        <input type="password" name="password" placeholder="Mật khẩu" id="password"
                                            value="{{ old('password') }}"
                                            class="form-control @if ($errors->has('password')) is-invalid @endif">
                                        <span class="input-group-text" onclick="passwordShowHide();">
                                            <i class="fas fa-eye" id="show_eye"></i>
                                            <i class="fas fa-eye-slash d-none" id="hide_eye"></i>
                                        </span>
                                        @if ($errors->has('password'))
                                            <div class="invalid-feedback">{{ $errors->first('password') }}</div>
                                        @endif
                                    </div>
                                    <div class="d-grid gap-2 col-12 mx-auto text-center">
                                        <button type="submit" class="btn btn-md btn-block mt-4">Đăng nhập</button>
                                    </div>
                                </form>
                                <div class="row mt-30">
                                    <div class="col-6">
                                        <a href="{{ route('auth.forgotPassword') }}">
                                            Quên mật khẩu ?
                                        </a>
                                    </div>
                                </div>
                                <div class="with-social mb-20">
                                    <div class="line"></div>
                                    <small class="or text-center">Hoặc</small>
                                    <div class="line"></div>
                                </div>
                                <div class="with-social mb-50">
                                    <div class="me-4">
                                        <span>Đăng nhập với</span>
                                    </div>
                                    <div class="facebook text-center me-3">
                                        <a href="{{ route('auth.loginSocial', ['provider' => 'facebook']) }}"
                                            class="text-light">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                    </div>
                                    <div class="twitter text-center me-3">
                                        <a href="{{ route('auth.loginSocial', ['provider' => 'google']) }}"
                                            class="text-light">
                                            <i class="fab fa-google"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="row mb-40 text-center">
                                    <p class="text-secondary">Bạn mới biết đến {{ getConfigDb('config_name') }}?
                                        &nbsp<a href="{{ route('auth.register') }}" class="text-danger">Đăng ký</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
