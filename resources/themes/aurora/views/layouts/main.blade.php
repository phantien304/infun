<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">

<head>
    @include('web::share.head')
    @yield('style')
    @yield('script_header')
</head>

<body class="bg-canvas text-ink antialiased">
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
