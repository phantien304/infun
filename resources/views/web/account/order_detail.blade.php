@extends('client.infunstudio.layouts.main_account')
@section('style')
    <style type="text/css">
        .cancel-order {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .cancel-order:hover {
            background-color: #dc3545;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table-bordered thead td, .table-bordered thead th {
            border-bottom-width: 1px;
        }

        .table-bordered td, .table-bordered th {
            border: 1px solid #dee2e6 !important;
        }

        .modal-body h4 {
            font-size: 21px;
            margin-top: 0;
            margin-bottom: 0.5em;
            color: rgba(0, 0, 0, 0.85);
            font-weight: 500;
        }

        .modal-body ul {
            padding-left: 16px;
            line-height: 1.6;
            list-style: none;
        }

        .modal-body ul li {
            position: relative;
        }

        .modal-body ul li::before {
            content: "• ";
            color: rgb(176, 176, 176);
            font-size: 26px;
            position: absolute;
            top: -8px;
            left: -16px;
        }

        @media only screen and (max-width: 480px) {
            .table td {
                display: table-cell;
            }
        }
    </style>
@stop
@section('content')
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Chi tiết đơn hàng #{{ $entity->invoice_no }}</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered mt-3">
                    <thead>
                    <tr>
                        <th class="left" colspan="2">Chi tiết đơn hàng</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="left" style="width: 50%;">
                            <b>Hóa đơn:</b>&nbsp;
                            {{ $entity->invoice_prefix }}-{{ $entity->invoice_no }}<br>
                            <b>Mã đơn hàng:</b>&nbsp;
                            {{ $entity->invoice_no }}<br>
                            <b>Ngày tạo:</b>&nbsp;
                            {!! \Carbon\Carbon::parse($entity->created_at)->format('H:i m/d/Y') !!}
                        </td>
                        <td class="left" style="width: 50%;">
                            <b>Hình thức thanh toán:</b>&nbsp;
                            @if(isset($entity->payment->paymentDescription))
                                {!! $entity->payment->paymentDescription->name !!}<br>
                            @endif
                            @if($entity->order_status_id == getConfigDb('order_payment_waiting_status_id'))
                                @if(\Carbon\Carbon::parse($entity->created_at)->diffInMinutes() < 240)
                                    <p class="text-danger" style="font-size: 14px; font-style: italic;">
                                        Thanh toán thất bại. Vui lòng thanh toán lại
                                    </p>
                                    <div class="text-center pt-1 mt-1">
                                        <a class="btn btn-danger"
                                           href="{{ route('checkout.repayment', ['id' => $entity->id]) }}">
                                            Thanh toán lại
                                        </a>
                                    </div>
                                    <p class="mt-3" style="font-size: 14px;">
                                        Đơn hàng sẽ tự huỷ nếu chưa được thanh toán trong vòng 4 tiếng kể từ thời
                                        điểm đặt hàng.
                                    </p>
                                @else
                                    <p class="text-danger" style="font-size: 14px; font-style: italic;">
                                        Chưa thực hiện thanh toán. Vui lòng tiến hành thanh toán lại hoặc đặt lại
                                        đơn hàng.
                                    </p>
                                @endif
                            @elseif($entity->order_status_id == getConfigDb('order_payment_success_status_id'))
                                <p class="text-danger" style="font-size: 14px; font-style: italic;">
                                    Thanh toán thành công.
                                </p>
                            @endif
                        </td>
                    </tr>
                    </tbody>
                </table>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th class="left" colspan="2">Địa chỉ giao hàng</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="left" style="width: 50%;">
                            <b>Họ tên:</b>&nbsp;
                            {!! $entity->full_name !!}<br>
                            <b>Địa chỉ:</b>&nbsp;
                            {!! implode(', ', [$entity->address, $entity->ward, $entity->district, $entity->zone ]) !!}
                            <br>
                            <b>Số điện thoại:</b>&nbsp;
                            {!! $entity->telephone !!}<br>
                        </td>
                        <td class="left" style="width: 50%;">
                            <b>Hình thức giao hàng:</b>&nbsp;{!! array_get($entity, 'carrier.name') !!}<br>
                        </td>
                    </tr>
                    </tbody>
                </table>
                @php $products = $entity->ordersProducts;@endphp
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Tên sản phẩm</th>
                        <th>Model</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Tạm tính</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>
                                @if(isset($product->product) && isset($product->product->productDescription))
                                    <a href="{!! $product->product->productDescription->getUrlClient() !!}">
                                        {!! $product->name !!}
                                    </a>
                                @else
                                    {!! $product->name !!}
                                @endif
                                <br>
                                <small>
                                    @if(isset($product->ordersProductOptions) && filled($product->ordersProductOptions))
                                        @foreach($product->ordersProductOptions as $opt)
                                            @php $child = unserialize($opt->children);@endphp
                                            <p>- {{ $opt->name }}
                                                : {{ $opt->value }}
                                                @if($opt->variation == 1)
                                                    @php $optChildValue = []; $optChildName = '';@endphp
                                                    @foreach($child as $chd)
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
                                </small>
                            </td>
                            <td>{{ $product->model }}</td>
                            <td>{{ $product->quantity }}</td>
                            <td>{{ number_format($product->price, 0, '', ',').'đ' }}</td>
                            <td>{{ number_format($product->total, 0, '', ',').'đ' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    @php $totalData = $entity->ordersTotals;@endphp
                    @foreach($totalData as $item)
                        <tr>
                            <td colspan="3"></td>
                            <td class="right"><b>{!! $item->title !!}</b></td>
                            <td class="right">{!! number_format($item->value, 0, '', ',').'đ' !!}</td>
                        </tr>
                    @endforeach
                    </tfoot>
                </table>
                @if(!in_array($entity->order_status_id, getConfigDb('config_order_member_not_delete')))
                    <table class="table">
                        <tbody>
                        <tr>
                            <td class="float-end p-0 pt-10" style="border: none;">
                                <a class="btn text-light cancel-order" title="Hủy đơn hàng"
                                   data-bs-toggle="modal" href="#alertCancelOrder">Hủy đơn hàng</a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                @endif
                <table class="table">
                    <tbody>
                    <tr>
                        <td class="float-start p-0 pt-10" style="border: none;">
                            <a href="{{ route('account.orders') }}" class="btn btn-link">
                                << Quay lại đơn hàng của tôi
                            </a>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="alertCancelOrder" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body"><h4>Thời gian hoàn tiền:</h4>
                    <ul>
                        <li>3 - 5 ngày làm việc với Ví ZaloPay, thẻ ATM nội địa</li>
                        <li>5 - 7 ngày làm việc với thẻ Visa/ Master/ JCB</li>
                        <li>Thời gian hoàn tiền có thể dao động trong khoảng 1-3 tuần làm việc - tùy theo chính sách của
                            từng ngân hàng. Ngày làm việc không gồm thứ 7, CN và ngày lễ
                        </li>
                    </ul>
                    <div>Bạn có đồng ý hủy đơn hàng không?</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-target="#confirmCancelOrder"
                            data-bs-toggle="modal" data-bs-dismiss="modal">Đồng ý
                    </button>
                    <button type="button" class="btn" data-bs-dismiss="modal">Không</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmCancelOrder" tabindex="-1" role="dialog">
        <form action="{{ route('account.cancelOrder') }}" method="post">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="title">
                            <h4>Lý do hủy đơn hàng #{{ $entity->invoice_no }}</h4>
                        </div>
                        <div class="form mb-30">
                            <div class="mb-3" id="return_reason">
                                <select class="form-control" name="return_reason">
                                    <option value="">Chọn lý do hủy</option>
                                    <option value="Đổi hình thức thanh toán">
                                        Đổi hình thức thanh toán
                                    </option>
                                    <option value="Không còn nhu cầu">
                                        Không còn nhu cầu
                                    </option>
                                    <option value="Đặt trùng">
                                        Đặt trùng
                                    </option>
                                    <option value="Thời gian giao hàng quá lâu/sớm">
                                        Thời gian giao hàng quá lâu/sớm
                                    </option>
                                    <option value="Thêm/bớt sản phẩm">
                                        Thêm/bớt sản phẩm
                                    </option>
                                    <option value="Thay đổi địa chỉ giao hàng">
                                        Thay đổi địa chỉ giao hàng
                                    </option>
                                    <option value="Khác">Khác</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <textarea placeholder="Xin cho biết lý do hủy đơn hàng" name="comment"
                                          class="form-control"></textarea>
                            </div>
                            <input type="hidden" name="order_id" value="{{ $entity->id }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="confirm-delete">Đồng ý</button>
                        <button type="button" class="btn" data-bs-dismiss="modal">Không</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@stop
