{{--
    Total summary rows — partial dùng chung giữa SSR render
    (web::checkout.index + web::checkout.cart) và AJAX update DOM
    (CheckoutCouponController::apply trả HTML từ partial này).

    Input: $totalData (array<int, {code,title,text,value}>)
--}}
@foreach ($totalData as $i => $item)
    @if ($i == count($totalData) - 1)
        <tr>
            <td scope="col" colspan="2">
                <div class="divider-2 mt-10 mb-10"></div>
            </td>
        </tr>
    @endif
    <tr>
        <td class="cart_total_label">
            <h6 class="text-muted">{!! $item['title'] !!}</h6>
        </td>
        <td class="cart_total_amount">
            <h5 class="text-brand text-end">{!! $item['text'] !!}</h5>
        </td>
    </tr>
@endforeach
