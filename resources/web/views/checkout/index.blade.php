@php
    $address = json_decode(getCookie(setting('cookie.user.address'), '[]'), true);
    $addressVisitor = [];
    if (filled($address)) {
        $addressVisitor = array_filter($address, function ($k) {
            return $k['is_default'] == 1;
        });
        $addressVisitor = array_values($addressVisitor)[0];
    }
    $userAddressId = old('user_address_id', data_get($addressVisitor, 'id', 0));
    $zoneIdCookie = getCookie(setting('cookie.shipping_zone'), '');
    $zoneNameCookie = '';
    if (filled($zoneIdCookie)) {
        $zoneCookie = $zones->firstWhere('id', $zoneIdCookie);
        $zoneNameCookie = $zoneCookie ? $zoneCookie->name : '';
    }
    $zoneId = old('zone_id', data_get($addressVisitor, 'zone_id', $zoneIdCookie));
    $fullName = old('full_name', data_get($addressVisitor, 'full_name', ''));
    $telephone = old('telephone', data_get($addressVisitor, 'telephone', ''));
    $zoneName = old('zone_name', data_get($addressVisitor, 'zone_name', $zoneNameCookie));
    $districtId = old('district_id', data_get($addressVisitor, 'district_id', 0));
    $districtName = old('district_name', data_get($addressVisitor, 'district_name', ''));
    $wardId = old('ward_id', data_get($addressVisitor, 'ward_id', 0));
    $wardName = old('ward_name', data_get($addressVisitor, 'ward_name', ''));
    $inputAddress = old('address', data_get($addressVisitor, 'address', ''));
    $fullAddress = data_get($addressVisitor, 'full_address', '');
    $carrierCode = old('carrier_code', setting('shipping.default'));
    $paymentCode = old('payment_code', setting('payment.default'));
@endphp
@extends('web::layouts.main')
@section('script_header')
    <script type="text/javascript">
        var checkoutShipping = '{{ route('checkout.shipping') }}';
        var carrierCode = '{{ $carrierCode }}';
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb_v2', ['titlePage' => 'Thanh toán'])
    <div class="container-xl mb-80 mt-50">
        <form action="{{ route('checkout.saveOrder') }}" method="post" enctype="multipart/form-data" id="saveOrder"
            role="form">
            {{-- Idempotency token: chống double-submit tạo trùng đơn (guest lẫn user) --}}
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey ?? '' }}">
            <div class="row cart-wrap">
                <div class="col-lg-12">
                    <h1 class="heading-2">Thanh toán</h1>
                </div>
                <div class="col-lg-12 mt-3">
                    @if (session()->has('success'))
                        <div class="alert alert-success">{!! session()->get('success') !!}</div>
                    @endif
                    @if (session()->has('failed'))
                        <div class="alert alert-danger">{!! session()->get('failed') !!}</div>
                    @endif
                    @if (session()->has('info'))
                        <div class="alert alert-warning">{!! session()->get('info') !!}</div>
                    @endif
                    @if (filled($error))
                        <div class="alert alert-danger">{!! $error !!}</div>
                    @endif
                </div>
                @if ($countProduct)
                    <div class="col-lg-9 cart-list">
                        <div class="content-infunstudio-package mt-3">
                            <div class="box nopadding nopadding">
                                <div class="box-heading">
                                    <i class="fas fa-map-marker-alt"></i>&nbsp;<span class="title">Thông tin nhận
                                        hàng</span>
                                </div>
                                <div class="b"></div>
                                <div class="box-content p-3">
                                    <div class="row mt-4">
                                        <div class="col-lg-6">
                                            <label for="inputFullName">Họ và tên&nbsp;<span
                                                    class="required">*</span></label>
                                            <input type="text" name="full_name" id="inputFullName"
                                                @if (auth()->check()) readonly @endif placeholder="Họ và tên"
                                                value="{{ $fullName }}"
                                                class="form-control @if ($errors->has('full_name')) is-invalid @endif">
                                            @if ($errors->has('full_name'))
                                                <div class="invalid-feedback">{{ $errors->first('full_name') }}</div>
                                            @endif
                                        </div>
                                        <div class="col-lg-6">
                                            <label for="inputPhone">Số điện thoại&nbsp;<span
                                                    class="required">*</span></label>
                                            <input type="text" id="inputPhone" name="telephone"
                                                @if (auth()->check()) readonly @endif
                                                placeholder="Số điện thoại" value="{{ $telephone }}"
                                                class="form-control @if ($errors->has('telephone')) is-invalid @endif">
                                            @if ($errors->has('telephone'))
                                                <div class="invalid-feedback">{{ $errors->first('telephone') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @if (auth()->check())
                                        <input type="hidden" name="email" id="email"
                                            value="{{ auth()->user()->email }}">
                                    @else
                                        <div class="row mt-4">
                                            <div class="col-lg-12">
                                                <label for="inputEmail">Email</label>
                                                <input type="email" id="inputEmail" name="email"
                                                    placeholder="Nhập email" value="{{ old('email') }}"
                                                    class="form-control @if ($errors->has('email')) is-invalid @endif">
                                                @if ($errors->has('email'))
                                                    <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    <div class="row mt-4">
                                        <div class="col-lg-12">
                                            <label for="inputNote">Ghi chú</label>
                                            <textarea rows="3" id="inputNote" name="comment" placeholder="Nhập ghi chú (nếu có)">{{ old('comment') }}</textarea>
                                        </div>
                                    </div>
                                    <input type="hidden" name="user_address_id" id="idUserAddress"
                                        value="{{ $userAddressId }}">
                                    <input type="hidden" name="zone_id" id="zoneId" value="{{ $zoneId }}">
                                    <input type="hidden" name="zone_name" id="zoneName" value="{{ $zoneName }}">
                                    <input type="hidden" name="district_id" id="districtId" value="{{ $districtId }}">
                                    <input type="hidden" name="district_name" id="districtName"
                                        value="{{ $districtName }}">
                                    <input type="hidden" name="ward_id" id="wardId" value="{{ $wardId }}">
                                    <input type="hidden" name="ward_name" id="wardName" value="{{ $wardName }}">
                                    <input type="hidden" name="address" id="address" value="{{ $inputAddress }}">
                                </div>
                            </div>
                        </div>
                        <div class="content-infunstudio-package mt-3">
                            <div class="box latest nopadding">
                                <div class="box-heading">
                                    <i class="fas fa-truck"></i>
                                    &nbsp;<span class="title">Vận chuyển</span>
                                </div>
                                <div class="b"></div>
                                <div class="box-content p-3 payment_option">
                                    <div class="col-xl-12 mb-2 pt-2">
                                        <span class=""><b>Hình thức vận chuyển</b></span>
                                    </div>
                                    @foreach ($carriers as $carrier)
                                        <div id="{{ $carrier->code }}"
                                            class="custom-control custome-radio mt-3 pt-3 border-top @if ($errors->has('carrier_code')) is-invalid @endif">
                                            <input type="radio" name="carrier_code" value="{{ $carrier->code }}"
                                                id="input{{ $carrier->code }}" class="form-check-input"
                                                @if ($carrierCode == $carrier->code) checked @endif>
                                            <label class="form-check-label" for="input{{ $carrier->code }}">
                                                {{ $carrier->name }}:&nbsp;
                                                <span class="text-price">
                                                    <b>Phí vận chuyển <span class="price"></span></b>
                                                </span>
                                            </label>
                                        </div>
                                    @endforeach
                                    @if ($errors->has('carrier_code'))
                                        <div class="invalid-feedback">{{ $errors->first('carrier_code') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="box latest nopadding mt-4 mb-30">
                                <div class="box-heading">
                                    <i class="far fa-credit-card"></i>&nbsp;<span class="title">Thanh toán</span>
                                </div>
                                <div class="b"></div>
                                <div class="box-content p-3 payment_option">
                                    <div class="col-xl-12 mb-2 pt-2">
                                        <span class=""><b>Phương thức thanh toán</b></span>
                                    </div>
                                    @foreach ($payments as $payment)
                                        @if ($payment->description)
                                            <div
                                                class="custom-control custome-radio mt-3 pt-3 border-top @if ($errors->has('payment_code')) is-invalid @endif">
                                                <input type="radio" name="payment_code" value="{{ $payment->code }}"
                                                    id="input{{ $payment->code }}" class="form-check-input"
                                                    @checked($paymentCode == $payment->code)>
                                                <label class="form-check-label" for="input{{ $payment->code }}">
                                                    {{ $payment->description->name }}
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                    @if ($errors->has('payment_code'))
                                        <div class="invalid-feedback">{{ $errors->first('payment_code') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 sidebar primary-sidebar">
                        <div class="row">
                            <div class="col-xl-12 mb-3">
                                <div class="wrapper rounded border pt-20 p-10">
                                    <div class="d-flex align-items-end justify-content-between mb-20">
                                        <h6>Địa chỉ nhận hàng</h6>
                                        <h6 class="text-muted">
                                            <b class="float-right text-primary" style="cursor: pointer;"
                                                data-bs-toggle="modal" data-bs-target="#chooseAddress">
                                                Thay đổi
                                            </b>
                                        </h6>
                                    </div>
                                    <div class="divider-2 mb-10"></div>
                                    <div class="list-group">
                                        @php
                                            $addressCustomer = setting('cookie.user.address');
                                            if (getCookie($addressCustomer)) {
                                                $address = json_decode(getCookie($addressCustomer), true) ?: [];
                                                foreach ($address as $item) {
                                                    if (!empty($item['is_default'])) {
                                                        echo $item['full_address'] ?? '';
                                                        break;
                                                    }
                                                }
                                            }
                                        @endphp
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-12 mb-3">
                                <div class="wrapper rounded border pt-20 p-10">
                                    <div class="mb-20">
                                        <h6>Đơn hàng của bạn</h6>
                                    </div>
                                    <div class="divider-2 mb-10"></div>
                                    <div class="box-content">
                                        <div class="row product-items last">
                                            @foreach ($products as $product)
                                                <div class="col-xl-12 col-lg-12 product-cols first">
                                                    <div class="product-block">
                                                        <div class="image d-flex justify-content-center align-self-center">
                                                            <a href="{!! $product['url'] !!}"
                                                                title="{!! $product['name'] !!}">
                                                                <img src="{{ thumbnail((string) ($product['image'] ?? ''), 100, 100) }}"
                                                                    alt="{!! $product['name'] !!}">
                                                            </a>
                                                        </div>
                                                        <div class="product-meta">
                                                            <div class="left">
                                                                <h3 class="name">
                                                                    <a href="{!! $product['url'] !!}">
                                                                        {!! $product['name'] !!}
                                                                        @if (!$product['in_stock'])
                                                                            <span class="stock" style="font-size: 13px;">
                                                                                <b style="color: #d81800;">(***)</b></span>
                                                                        @endif
                                                                    </a>
                                                                </h3>
                                                                <div class="cart-option">
                                                                    @if (count($product['option']))
                                                                        @foreach ($product['option'] as $opt)
                                                                            <p>- {{ $opt['name'] }}
                                                                                : {{ $opt['value'] }}
                                                                                @if ($opt['variation'] == 1)
                                                                                    @php
                                                                                        $optChildValue = [];
                                                                                        $optChildName = '';
                                                                                    @endphp
                                                                                    @foreach ($opt['child'] as $chd)
                                                                                        @php
                                                                                            $optChildName =
                                                                                                $chd['name'];
                                                                                            $optChildValue[] =
                                                                                                $chd['value'];
                                                                                        @endphp
                                                                                    @endforeach
                                                                                    - {!! $optChildName . ': ' . implode(', ', $optChildValue) !!}
                                                                                @endif
                                                                            </p>
                                                                        @endforeach
                                                                    @endif
                                                                </div>
                                                                <div class="quantity">
                                                                    Số lượng:
                                                                    {{ $product['quantity'] }}
                                                                </div>
                                                                <div class="price">
                                                                    Thành tiền :
                                                                    {{ money($product['total']) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                            @include('web::checkout._gift_items_list')
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-12 mt-3 mb-3">
                                                <div class="table-responsive">
                                                    {{-- Debug panel — bật bằng ?debug=1 trên URL --}}
                                                    @if (request()->boolean('debug'))
                                                        <div class="alert alert-info" style="font-size: 11px;">
                                                            <b>DEBUG totalData</b> ({{ count($totalData) }} rows):<br>
                                                            @foreach ($totalData as $i => $row)
                                                                #{{ $i }} code={{ $row['code'] }} title={{ strip_tags($row['title']) }} text={{ strip_tags($row['text']) }} value={{ $row['value'] }}<br>
                                                            @endforeach
                                                            <br><b>Session</b>:
                                                            applied_coupons={{ json_encode((array) session('checkout.applied_coupons', [])) }}
                                                        </div>
                                                    @endif
                                                    <table class="table custom no-border">
                                                        <tbody id="total-data">
                                                            @include('web::checkout._total_data_rows')
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-12">
                                <div id="coupon-promo-row-container">
                                    @include('web::checkout._coupon_promo_row')
                                </div>
                                @if (isset($gifts) && $gifts->count())
                                    <div id="gift-promo-row-container">
                                        @include('web::checkout._gift_promo_row')
                                    </div>
                                @endif
                                <div id="voucher-promo-row-container">
                                    @include('web::checkout._voucher_promo_row')
                                </div>
                                <div id="reward-promo-row-container">
                                    @include('web::checkout._reward_promo_row')
                                </div>
                            </div>
                            <div class="col-xl-12 mt-3">
                                <div class="d-grid gap-2 col-12 mx-auto text-center">
                                    <button type="submit" class="btn btn-lg btn-block" data-order-submit>
                                        Đặt hàng<i class="fi-rs-sign-out ml-15"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    </div>
    @if ($countProduct)
        @include('web::checkout._choose_address')
        {{-- Modal MUST live outside the saveOrder <form> to avoid nested
             forms (the modal contains its own AJAX manual-code input). --}}
        @include('web::checkout._coupon_modal')
        @if (isset($gifts) && $gifts->count())
            @include('web::checkout._gift_modal')
        @endif
        @include('web::checkout._voucher_modal')
    @endif

    {{-- Chống double-click nút Đặt hàng (lớp frontend của idempotency).
         Chỉ khoá nút khi form thực sự submit; tự mở lại sau 8s phòng khi
         validation phía client chặn submit, tránh kẹt nút. --}}
    <script>
        (function () {
            var form = document.getElementById('saveOrder');
            if (!form) return;
            form.addEventListener('submit', function () {
                var btn = form.querySelector('[data-order-submit]');
                if (!btn || btn.dataset.locked === '1') return;
                btn.dataset.locked = '1';
                btn.classList.add('disabled');
                btn.setAttribute('aria-busy', 'true');
                setTimeout(function () {
                    btn.dataset.locked = '';
                    btn.classList.remove('disabled');
                    btn.removeAttribute('aria-busy');
                }, 8000);
            });
        })();
    </script>
@stop
