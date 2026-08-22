{{--
    Trang xác nhận huỷ nhận tin, mở từ link trong mail marketing.

    CỐ TÌNH không @extends('web::layouts.main'): layout chính cần bộ biến SEO
    do Controller::render() bơm vào (titleSeo, breadcrumbSchema…). Trang này
    được mở từ hộp thư, chỉ nói đúng một câu — kéo cả layout theo là gánh phụ
    thuộc không cần thiết cho một đường link phải luôn hoạt động.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ trans('mailer.marketing.unsubscribed_title') }} — {{ getConfigDb('config_name') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
               margin: 0; padding: 48px 16px; background: #f7f7f8; color: #1f2328; }
        .box { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px;
               padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.6; margin: 0 0 16px; }
        a { color: #0f74a8; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ trans('mailer.marketing.unsubscribed_title') }}</h1>
        <p>{{ trans('mailer.marketing.unsubscribed_body') }}</p>
        <p><a href="{{ route('home') }}">{{ getConfigDb('config_name') }}</a></p>
    </div>
</body>
</html>
