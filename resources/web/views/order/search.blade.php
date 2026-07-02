@php
    $imageDefault = thumbnail(getModuleConfig('img_default'), 800, 354);
    $urlSearch = route('order.search');
@endphp
@extends('web::layouts.main')
@section('meta')
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}"/>
    <meta property="og:type" content="article"/>
    <meta property="og:url" itemprop="url" content="{!! $urlSearch !!}"/>
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $imageDefault !!}"/>
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title"/>
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description"/>
    @include('web::share.structure._meta_common')
    <meta name="twitter:card" content="summary"/>
    <meta name="twitter:url" content="{!! $urlSearch !!}"/>
    <meta name="twitter:title" content="{!! $titleSeo !!}"/>
    <meta name="twitter:description" content="{!! $descriptionSeo !!}"/>
    <meta name="twitter:image" content="{!! $imageDefault !!}"/>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
@stop
@section('content')
    @include('web::share.structure._breadcrumb_v2')
    <div class="page-content mt-30 mb-50">
        <div class="container-xl search-order">
            <div class="row justify-content-center mt-5">
                <div class="col-xl-8 col-md-12">
                    <div class="text-center mb-4">
                        <h3>Tra cứu đơn hàng</h3>
                    </div>
                    <form action="{{ route('order.search') }}" method="get" role="form" class="form-inline">
                        <div class="input-group flex-fill">
                            <input type="text" name="order_code" class="form-control" value="{{ $orderCode }}"
                                   placeholder="Nhập mã đơn hàng" style="height: auto;">
                            <button type="submit" class="btn btn-info">Tìm kiếm</button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($entity)
                <div class="mt-50" style="background:#fff;font-size:14px;">
                    <div class="row">
                        <div class="col-xl-4 mb-40">
                            <div class="card mb-3">
                                <div class="card-header"><b>THÔNG TIN ĐƠN HÀNG</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-5">Mã đơn hàng:</div>
                                        <div class="col-7"><b>{{ $entity->invoice_no }}</b></div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-5">Trạng thái:</div>
                                        <div class="col-7"><b>{{ data_get($entity, 'ordersStatus.name') }}</b></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header"><b>NGƯỜI NHẬN HÀNG</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-5">Họ và tên:</div>
                                        <div class="col-7"><b>{{ string2Stars($entity->full_name, 0, -5) }}</b></div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-5">Số điện thoại:</div>
                                        <div class="col-7"><b>{{ string2Stars($entity->telephone, 0, -4) }}</b></div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-5">Địa chỉ:</div>
                                        <div class="col-7"><b>{{ $entity->address }}</b></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 content-table mb-40">
                            <div class="card">
                                <div class="card-header"><b>TRẠNG THÁI ĐƠN HÀNG</b></div>
                                <div class="card-body progress-order">
                                    @foreach ($entity->ordersHistories as $item)
                                        <div class="d-flex mb-3">
                                            <div class="step-info">
                                                <div class="step-info__status-text">
                                                    <b>{{ data_get($item, 'ordersStatus.name') }}</b>
                                                </div>
                                                <div class="step-info__substate-text">{{ $item->comment }}</div>
                                                <div class="step-info__time text-muted">
                                                    {{ \Carbon\Carbon::parse($item->created_at)->format('H:i d/m/Y') }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 sidebar mb-40">
                            <div class="card">
                                <div class="card-header"><b>SẢN PHẨM</b></div>
                                <div class="card-body">
                                    @foreach ($entity->ordersProducts as $product)
                                        <div class="product-block mb-3 pb-3 border-bottom">
                                            <h3 class="name" style="font-size:14px;">{!! $product->name !!}</h3>
                                            <div class="cart-option text-muted">
                                                @foreach ($product->ordersProductOptions as $opt)
                                                    <p class="mb-0">- {{ $opt->name }}: {{ $opt->value }}</p>
                                                @endforeach
                                            </div>
                                            <div class="quantity">Số lượng: {{ $product->quantity }}</div>
                                            <div class="price">Thành tiền:
                                                {{ number_format((float) $product->total, 0, '', ',') }}đ</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif ($orderCode !== '')
                <div class="alert alert-warning text-center mt-50">
                    Không tìm thấy đơn hàng với mã <b>{{ $orderCode }}</b>.
                </div>
            @endif
        </div>
    </div>
@stop
