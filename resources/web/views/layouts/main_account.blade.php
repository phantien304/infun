<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    @include('web::share.head')
    @yield('style')
    @yield('script_header')
    <script type="text/javascript">
        if (window.location.hash && window.location.hash === '#_=_') {
            window.location.hash = '';
        }
    </script>
</head>

<body>
    @include('web::share.menu')
    @yield('banner')
    <main class="main">
        @include('web::share.structure._breadcrumb_v2')
        <div class="page-content pt-50 pb-50">
            <div class="container-xl form-login">
                <div class="row">
                    <div class="col-lg-12 mt-3 mb-30">
                        @if (session()->has('success'))
                            <div class="alert alert-success">{!! session()->get('success') !!}</div>
                        @endif
                        @if (session()->has('failed'))
                            <div class="alert alert-danger">{!! session()->get('failed') !!}</div>
                        @endif
                    </div>
                    <div class="col-lg-3 mb-40">
                        @include('web::account._menu_left')
                    </div>
                    @yield('content')
                </div>
            </div>
        </div>
    </main>
    @include('web::share.footer')
    @yield('script')
</body>

</html>
