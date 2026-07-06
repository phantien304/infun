<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/strict.dtd">
@php
    $productData = $data[0];
    $totalData = $data[1];
    $infoCustomer = $data[2];
@endphp
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ getConfigDb('config_name') }}</title>
</head>

<body style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000000;">
    <div style="width: 680px;">
        <p style="margin-top: 0px; margin-bottom: 20px;">
            {{ getConfigDb('config_name') }} - Bạn nhận được đơn hàng mới.
        </p>

        <table
            style="border-collapse: collapse; width: 100%; border-top: 1px solid #DDDDDD; border-left: 1px solid #DDDDDD; margin-bottom: 20px;">
            <thead>
                <tr>
                    <td style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: left; padding: 7px; color: #222222;"
                        colspan="2">Chi tiết đơn hàng
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td
                        style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: left; padding: 7px;">
                        <b>Mã đơn hàng:</b>
                        {{ getConfigDb('config_invoice_prefix') . '-' . $infoCustomer['uniqid'] }}<br />
                        <b>Ngày tạo đơn hàng:</b> {{ \Carbon\Carbon::now()->format('h:i m/d/Y') }}<br />
                        <b>Thanh toán:</b> {{ $infoCustomer['payment_name'] }}<br />
                    </td>
                    <td
                        style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: left; padding: 7px;">
                        <b>Họ tên:</b> {{ $infoCustomer['full_name'] }}<br />
                        <b>Email:</b> {{ $infoCustomer['email'] }}<br />
                        <b>Số điện thoại:</b> {{ $infoCustomer['telephone'] }}<br />
                        <b>Tình trạng đơn hàng:</b> {{ $infoCustomer['order_status'] }}<br />
                    </td>
                </tr>
            </tbody>
        </table>
        <table
            style="border-collapse: collapse; width: 100%; border-top: 1px solid #DDDDDD; border-left: 1px solid #DDDDDD; margin-bottom: 20px;">
            <thead>
                <tr>
                    <td
                        style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: left; padding: 7px; color: #222222;">
                        Sản phẩm
                    </td>
                    <td
                        style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: left; padding: 7px; color: #222222;">
                        Mã sản phẩm
                    </td>
                    <td
                        style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: right; padding: 7px; color: #222222;">
                        Số lượng
                    </td>
                    <td
                        style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: right; padding: 7px; color: #222222;">
                        Giá
                    </td>
                    <td
                        style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: right; padding: 7px; color: #222222;">
                        Thành tiền
                    </td>
                </tr>
            </thead>
            <tbody>

                @foreach ($productData as $product)
                    <tr>
                        <td
                            style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: left; padding: 7px;">
                            {!! $product['name'] !!}
                            @if (count($product['option']))
                                @foreach ($product['option'] as $opt)
                                    <p>- {{ $opt['name'] }}: {{ $opt['value'] }}
                                        @if ($opt['variation'] == 1)
                                            @php
                                                $optChildValue = [];
                                                $optChildName = '';
                                            @endphp
                                            @foreach ($opt['child'] as $chd)
                                                @php
                                                    $optChildName = $chd['name'];
                                                    $optChildValue[] = $chd['value'];
                                                @endphp
                                            @endforeach
                                            - {!! $optChildName . ': ' . implode(', ', $optChildValue) !!}
                                        @endif
                                    </p>
                                @endforeach
                            @endif
                        </td>
                        <td
                            style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: left; padding: 7px;">
                            {{ $product['model'] }}</td>
                        <td
                            style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: right; padding: 7px;">
                            {{ $product['quantity'] }}</td>
                        <td
                            style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: right; padding: 7px;">
                            {{ moneyAtBuy($product['price'], $infoCustomer['currency_code'] ?? null, $infoCustomer['currency_value'] ?? 1) }}
                        </td>
                        <td
                            style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: right; padding: 7px;">
                            {{ moneyAtBuy($product['total'], $infoCustomer['currency_code'] ?? null, $infoCustomer['currency_value'] ?? 1) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @foreach ($totalData as $total)
                    <tr>
                        <td style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: right; padding: 7px;"
                            colspan="4"><b>{!! $total['title'] !!}:</b></td>
                        <td
                            style="font-size: 12px;	border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: right; padding: 7px;">
                            {!! $total['text'] !!}</td>
                    </tr>
                @endforeach
            </tfoot>
        </table>
        <p style="margin-top: 0px; margin-bottom: 20px;">Vui lòng trả lời thư này nếu có bất kì câu hỏi nào.</p>
    </div>
</body>

</html>
