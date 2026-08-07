<title>
    @if (isset($titleSeo))
        {{ $titleSeo }}
    @endif
</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (isset($descriptionSeo))
    <meta name="description" content="{{ $descriptionSeo }}" />
@endif
<base href="{{ rtrim(config('app.url'), '/') }}/">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="{{ publicUrl(getConfigDb('config_icon')) }}" rel="icon" />
@yield('meta')
@if (isset($linkCanonical))
    <link href="{{ $linkCanonical }}" rel="canonical" />
@endif

@php $themeOwnsPage = \App\Helpers\ThemeManager::ownsView(); @endphp

@unless ($themeOwnsPage)
    <link rel="stylesheet" href="{{ publicUrl('web/css/main.css?v=' . getConfigDb('config_theme_version')) }}">
    <link rel="stylesheet" href="{{ publicUrl('web/css/custom.css?v=' . getConfigDb('config_theme_version')) }}">
@endunless

@if (\App\Helpers\ThemeManager::current())
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
@endif

@vite([\App\Helpers\ThemeManager::viteEntry()])
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
@include('web::share._theme_preview')
{{ getConfigDb('config_google_analytics') }}
