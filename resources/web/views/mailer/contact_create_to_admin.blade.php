<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ getConfigDb('config_name') }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000000;">
<div style="width: 680px;">
    <p style="margin-top: 0px; margin-bottom: 20px;">
        {!! getConfigDb('config_name') !!} - Bạn nhận được liên hệ mới.
    </p>
    <p style="margin-top: 0px; margin-bottom: 20px;">
        Họ và Tên: {{ array_get($data, 'name') }}
    </p>
    <p style="margin-top: 0px; margin-bottom: 20px;">
        Email: {{ array_get($data, 'email') }}
    </p>
    <p style="margin-top: 0px; margin-bottom: 20px;">
        Phone: {{ array_get($data, 'phone') }}
    </p>
    <p style="margin-top: 0px; margin-bottom: 20px;">
        Dịch vụ: {{ array_get($data, 'service') }}
    </p>
    <p style="margin-top: 0px; margin-bottom: 20px;">
        Nội dung: {{ array_get($data, 'content') }}
    </p>
</div>
</body>
</html>
