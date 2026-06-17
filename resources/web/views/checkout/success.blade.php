@extends('web::layouts.main')
@section('style')
    <style type="text/css">
        .wrapper {
            background-color: #FFFFFF;
            border-radius: 15px;
            border: 1px solid #ebeef2;
        }
    </style>
@stop
@section('content')
    @include('web::share.structure._breadcrumb_v2', ['titlePage' => 'Đặt hàng thành công'])
    <div class="page-content mt-50 mb-50">
        <div class="container-xl">
            <div class="row cart-wrap mb-5">
                <div class="col-xl-12">
                    <h1 class="mb-3">
                        @if($entity->order_status_id == getConfigDb('order_payment_success_status_id'))
                            {{ trans('messages.PaymentOrderSuccess') }}
                        @elseif($entity->order_status_id == getConfigDb('order_payment_failed_status_id'))
                            Đơn Hàng Của Bạn Đã Bị Hủy!
                        @else
                            Đơn Hàng Của Bạn Đang Được Xử Lý!
                        @endif
                    </h1>
                </div>
                <div class="col-xl-12 mt-3">
                    @if(session()->has('success'))
                        <div class="alert alert-success">{!! session()->get('success') !!}</div>
                    @endif
                    <div class="wrapper underline p-20">
                        <p class="mb-15">Chào <b>{{ $entity->full_name }},</b></p>
                        @if($entity->order_status_id == getConfigDb('order_payment_failed_status_id'))
                            <p class="mb-15">Quý khách đã hủy đơn hàng tại {{ getConfigDb('config_name') }}!</p>
                        @else
                            <p class="mb-15">Cảm ơn quý khách đã đặt hàng thành công
                                tại {{ getConfigDb('config_name') }}!</p>
                        @endif
                        <p class="mb-15">Mã số đơn hàng của bạn:
                            <a class="booking_code"
                               href="{{ route('order.search', ['order_code' => $entity->invoice_no]) }}">
                                {{ $entity->invoice_no }}
                            </a>
                            Click để xem lại
                            <a href="{{ route('order.search', ['order_code' => $entity->invoice_no]) }}"
                               class="txt_color_1" target="_blank">
                                Chi tiết đơn hàng
                            </a>
                        </p>
                        <div class="mb-15">
                            {!! getConfigDb('config_text_checkout_success') !!}
                        </div>
                        @if(filled($entity->email))
                            <p class="mb-15">
                                Thông tin chi tiết về đơn hàng đã được gửi đến địa chỉ
                                email <b>{{ $entity->email }}</b>. Nếu không tìm thấy vui lòng kiểm tra hộp thư Spam
                            </p>
                        @endif
                        <p class="mb-15">Mọi thắc mắc vui lòng liên hệ qua Email: <a class="txt_color_1"
                                                                                     href="mailto:{{ getConfigDb('config_email') }}"><b>{{ getConfigDb('config_email') }}</b></a>
                            hoặc Số điện thoại:
                            <b class="txt_color_1">{{ getConfigDb('config_telephone', '0942264439') }}</b>
                        </p>
                        <p class="mb-15">Cám ơn đã mua hàng tại {{ getConfigDb('config_name') }}!</p>
                        <p class="mb-15">
                            <a href="{{ getConfigDb('config_storage_domain') }}"
                               class="button btn btn-md">
                                <i class="fi-rs-arrow-left mr-10"></i>Tiếp tục mua hàng
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
