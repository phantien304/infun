{{--
  resources/cms/views/index.blade.php
  ----------------------------------------------------------
  Blade view duy nhất cho toàn bộ CMS SPA (giống bản Vue 2).
  Mọi route /cms/* đều render file này — React Router lo phần
  còn lại ở client.

  Bắt buộc có:
    1. <meta name="csrf-token"> để axios đọc
    2. <script> inject window.appSettings TRƯỚC @vite
    3. <div id="cms-app"> để app.jsx mount vào
    4. @vite([... app.jsx]) để load React bundle
  ----------------------------------------------------------
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CMS') }}</title>

    {{-- Inject config xuống client. Giữ nguyên cách cũ window.appSettings. --}}
    <script>
        window.appSettings = @json([
            'area'            => 'CMS',
            'urlCms'          => url('/'),
            'languages'       => $languages ?? [],
            'languageDefault' => $languageDefault ?? 'vi',
            'languageTexts'   => $languageTexts ?? new \stdClass(),
            // thêm key khác mà CMS cũ đang dùng tại đây
        ]);
    </script>

    @vite(['resources/cms/js/app.jsx'])
</head>
<body>
    <div id="cms-app"></div>
</body>
</html>
