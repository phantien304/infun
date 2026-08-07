<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    @include('web::share.head')
    @yield('style')
    @yield('script_header')
</head>

<body>
    @include('web::share.menu')
    @yield('banner')
    <main class="main">
        @yield('content')
    </main>
    @include('web::share.footer')
    @if (request()->is('/'))
        @include('web::share._theme_popup')
    @endif
    @yield('script')
</body>

</html>
