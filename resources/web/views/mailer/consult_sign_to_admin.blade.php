@php
    $product = $data['product'];
    $params = $data['params'];
    $options = $data['options'];
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/strict.dtd">
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ getConfigDb('config_name') }}</title>
</head>

<body style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000000;">
    <div style="width: 680px;">
        <p style="margin-top: 0px; margin-bottom: 20px;">
            {!! getConfigDb('config_name') !!} - Bạn nhận được yêu cầu tư vấn ngay
        </p>
        <p style="margin-top: 0px; margin-bottom: 20px;">
            Sản phẩm: {{ $product?->description?->name ?? '' }}
        </p>
        @foreach ($options as $option)
            @if ($option['type'] == 'file')
                <p style="margin-top: 0px; margin-bottom: 20px;">
                    {!! data_get($option, 'name') !!}: {{ config('app.url') . '/' . data_get($option, 'value') }}
                </p>
            @else
                <p style="margin-top: 0px; margin-bottom: 20px;">
                    {!! data_get($option, 'name') !!}: {{ data_get($option, 'value') }}
                </p>
            @endif
        @endforeach
        <p style="margin-top: 0px; margin-bottom: 20px;">
            Số lượng: {{ data_get($params, 'quantity') }}
        </p>
    </div>
</body>

</html>
