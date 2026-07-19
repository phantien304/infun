<title>
    @if (isset($titleSeo))
        {{ $titleSeo }}
    @endif
</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (isset($descriptionSeo))
    <meta name="description" content="{{ $descriptionSeo }}" />
@endif
<base href="{{ request()->getSchemeAndHttpHost() }}/">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="{{ publicUrl(getConfigDb('config_icon')) }}" rel="icon" />
@yield('meta')
@if (isset($linkCanonical))
    <link href="{{ $linkCanonical }}" rel="canonical" />
@endif
<link rel="stylesheet" href="{{ publicUrl('web/css/main.css?v=' . getConfigDb('config_theme_version')) }}">
<link rel="stylesheet" href="{{ publicUrl('web/css/custom.css?v=' . getConfigDb('config_theme_version')) }}">
@vite(['resources/web/css/app.css'])
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
{{ getConfigDb('config_google_analytics') }}
