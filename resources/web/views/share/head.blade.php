<title>
    @if (isset($titleSeo))
        {{ $titleSeo }}
    @endif
</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
@if (isset($descriptionSeo))
    <meta name="description" content="{{ $descriptionSeo }}" />
@endif
<base href="{{ getConfigDb('config_storage_domain') }}/">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="{{ publicUrl(getConfigDb('config_icon')) }}" rel="icon" />
@yield('meta')
@if (isset($linkCanonical))
    <link href="{{ $linkCanonical }}" rel="canonical" />
@endif
{{--
    Coexistence Bootstrap → Tailwind migration:
    main.css + custom.css   = theme legacy (giữ trong giai đoạn migration, là
                              nơi chứa visual identity custom — header-area,
                              main-menu, ...).
    @vite('resources/css/app.css') = Tailwind v4 build, output qua hash file
                              tự bust cache (KHÔNG cần `config_theme_version`).
                              Load SAU 2 file legacy để utility Tailwind override
                              được khi blade dùng class mới.
--}}
<link rel="stylesheet" href="{{ publicUrl('web/css/main.css?v=' . getConfigDb('config_theme_version')) }}">
<link rel="stylesheet" href="{{ publicUrl('web/css/custom.css?v=' . getConfigDb('config_theme_version')) }}">
@vite(['resources/web/css/app.css'])
{{--
    Alpine.js — interactive (modal, dropdown, collapse mobile menu) thay
    Bootstrap JS data-bs-* sau migration. CDN defer để không block render.
    Coexist với Bootstrap JS (data-bs-toggle còn dùng ở page chưa convert).
--}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
{{ getConfigDb('config_google_analytics') }}
