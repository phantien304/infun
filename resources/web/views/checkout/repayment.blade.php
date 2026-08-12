@php
    $paymentCode = old('payment_code', setting('payment.default'));
@endphp
@section('style')
    <style type="text/css">
        .address .name {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .address .street,
        .address .phone {
            padding: 2px 0;
        }

        .address span {
            display: block;
            font-size: 14px;
        }
    </style>
@stop
@extends('web::layouts.main')
@section('content')
    @include('web::share.structure._breadcrumb_v2', ['titlePage' => 'Thanh toán'])
    <div class="container-xl mb-80 mt-50">
        <form action="{{ route('checkout.saveRepayment') }}" method="post" enctype="multipart/form-data" role="form">
            <div class="row flex-row-reverse cart-wrap">
                <div class="col-lg-12">
                    <h1 class="heading-2">Thanh toán lại</h1>
                </div>
                <div class="col-lg-12 mt-3">
                    @if (session()->has('success'))
                        <div class="alert alert-success">{!! session()->get('success') !!}</div>
                    @endif
                    @if (session()->has('failed'))
                        <div class="alert alert-danger">{!! session()->get('failed') !!}</div>
                    @endif
                    @if (filled($error))
                        <div class="alert alert-danger">{!! $error !!}</div>
                    @endif
                </div>
                <div class="col-lg-3 sidebar primary-sidebar">
                    <div class="row">
                        <div class="col-xl-12 mb-3">
                            <div class="wrapper rounded border pt-20 p-10">
                                <div class="mb-20">
                                    <h6>Địa chỉ nhận hàng</h6>
                                </div>
                                <div class="divider-2 mb-10"></div>
                                <div class="list-group address">
                                    <span class="name">{{ $entity->full_name }}</span>
                                    <span class="street">
                                        {{ implode(', ', [$entity->address, $entity->ward, $entity->district, $entity->zone]) }}
                                    </span>
                                    <span class="phone">Điện thoại: {{ $entity->telephone }}</span>
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
                                        @php $products = $entity->ordersProducts;@endphp
                                        @foreach ($products as $product)
                                            @php
                                                $productEntity = $product->product ?? null;
                                                $productDescription = $productEntity?->description;
                                                $productUrl = '#';
                                                if ($productEntity && $productDescription) {
                                                    $productUrl = buildUrl(
                                                        resolveSlug($productDescription->slug ?? null, $product->name),
                                                        getModuleConfig('url.product'),
                                                        (int) $productEntity->id,
                                                    );
                                                }
                                                $productImage = $productEntity?->image ?? '';
                                                $thumbUrl = thumbnail($productImage, 60, 60);
                                            @endphp
                                            <div class="col-xl-12 col-lg-12 product-cols first">
                                                <div class="product-block">
                                                    <div class="image d-flex justify-content-center align-self-center">
                                                        @if ($productDescription)
                                                            <a href="{!! $productUrl !!}"
                                                                title="{!! $product->name !!}">
                                                                <img src="{!! $thumbUrl !!}"
                                                                    alt="{!! $product->name !!}">
                                                            </a>
                                                        @else
                                                            <img src="{!! $thumbUrl !!}"
                                                                alt="{!! $product->name !!}">
                                                        @endif
                                                    </div>
                                                    <div class="product-meta">
                                                        <div class="left">
                                                            <h3 class="name">
                                                                @if ($productDescription)
                                                                    <a href="{!! $productUrl !!}"
                                                                        title="{!! $product->name !!}">
                                                                        {!! $product->name !!}
                                                                    </a>
                                                                @else
                                                                    {!! $product->name !!}
                                                                @endif
                                                            </h3>
                                                            <div class="cart-option">
                                                                @if (count($product->ordersProductOptions))
                                                                    @foreach ($product->ordersProductOptions as $opt)
                                                                        @php
                                                                            $child = [];
                                                                            if (
                                                                                (int) $opt->variation === 1 &&
                                                                                filled($opt->children)
                                                                            ) {
                                                                                try {
                                                                                    $unserialized = @unserialize((string) $opt->children, ['allowed_classes' => false]);
                                                                                    if (is_array($unserialized)) {
                                                                                        $child = $unserialized;
                                                                                    }
                                                                                } catch (\Throwable) {
                                                                                    $child = [];
                                                                                }
                                                                            }
                                                                        @endphp
                                                                        <p>- {{ $opt->name }}
                                                                            : {{ $opt->value }}
                                                                            @if ($opt->variation == 1 && filled($child))
                                                                                @php
                                                                                    $optChildValue = [];
                                                                                    $optChildName = '';
                                                                                @endphp
                                                                                @foreach ($child as $chd)
                                                                                    @php
                                                                                        $optChildName =
                                                                                            $chd['name'] ??
                                                                                            $optChildName;
                                                                                        $optChildValue[] =
                                                                                            $chd['value'] ?? '';
                                                                                    @endphp
                                                                                @endforeach
                                                                                - {!! $optChildName . ': ' . implode(', ', array_filter($optChildValue)) !!}
                                                                            @endif
                                                                        </p>
                                                                    @endforeach
                                                                @endif
                                                            </div>
                                                            <div class="quantity">
                                                                Số lượng: {{ $product->quantity }}
                                                            </div>
                                                            <div class="price">
                                                                Thành tiền :
                                                                {{ money($product->total) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="row">
                                        <div class="col-xl-12 mt-3 mb-3">
                                            <div class="table-responsive">
                                                <table class="table custom no-border">
                                                    <tbody id="total-data">
                                                        @php $totalData = $entity->ordersTotals;@endphp
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
                                                                    <h6 class="text-muted">{!! $item->title !!}</h6>
                                                                </td>
                                                                <td class="cart_total_amount">
                                                                    <h5 class="text-brand text-end">{!! money($item->value) !!}
                                                                    </h5>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-9 cart-list">
                    <div class="content-infunstudio-package mt-3">
                        <div class="box latest nopadding">
                            <div class="box-heading">
                                <i class="fas fa-truck"></i>
                                &nbsp;<span class="title">Vận chuyển</span>
                            </div>
                            <div class="b"></div>
                            <div class="box-content p-3">
                                <div class="form-group">
                                    <div class="custom-control custom-radio mt-3">
                                        <label class="tit">
                                            @if (isset($entity->carrier))
                                                <img src="{{ thumbnail($entity->carrier->image, 100, 50) }}"
                                                    alt="{{ $entity->carrier->name }}" style="vertical-align: middle;" />
                                                {{ $entity->carrier->name }}
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box latest nopadding mt-4">
                            <div class="box-heading">
                                <i class="far fa-credit-card"></i>&nbsp;<span class="title">Thanh toán</span>
                            </div>
                            <div class="b"></div>
                            <div class="box-content p-3 payment_option">
                                <div class="col-xl-12 mb-2 pt-2">
                                    <span class=""><b>Phương thức thanh toán</b></span>
                                </div>
                                @foreach ($payments as $payment)
                                    @if ($payment?->description)
                                        <div
                                            class="custom-control custome-radio mt-3 pt-3 border-top @if ($errors->has('payment_code')) is-invalid @endif">
                                            <input type="radio" name="payment_code" value="{{ $payment->code }}"
                                                id="input{{ $payment->code }}" class="form-check-input"
                                                @if ($paymentCode == $payment->code) checked @endif>
                                            <label class="form-check-label" for="input{{ $payment->code }}">
                                                {{ $payment->description?->name }}
                                            </label>
                                        </div>
                                    @endif
                                @endforeach
                                @if ($errors->has('payment_code'))
                                    <div class="invalid-feedback mt-3">
                                        {{ $errors->first('payment_code') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="box latest nopadding">
                            <input type="hidden" name="order_id" value="{{ request()->route('id') }}">
                            <div class="pt-3 mt-3 mb-30">
                                <button type="submit" class="btn">
                                    Thanh toán lại<i class="fi-rs-sign-out ml-15"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@stop
