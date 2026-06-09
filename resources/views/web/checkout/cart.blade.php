@extends('web.layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! routeArea('checkout.cart') !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! thumbnail(getConstant('DEFAULT'), 800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    @include('web.share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! routeArea('checkout.cart') !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! thumbnail(getConstant('DEFAULT'), 800, 354) !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! $titleSeo !!}","alternateName":"{!! $descriptionSeo !!}","url":"{!! routeArea('checkout.cart') !!}"}
    </script>
@stop
@section('content')
    @include('web.share.structure._breadcrumb_v2', ['titlePage' => 'Giỏ hàng'])
    <div class="container-xl mb-80 mt-50">
        <div class="row mb-30">
            <div class="col-lg-8 mb-20">
                <h1 class="heading-2 mb-10">Giỏ hàng</h1>
                <div class="">
                    <h6 class="text-body">Có <span class="text-brand">{{ $countProduct }}</span> sản phẩm trong giỏ hàng
                    </h6>
                </div>
            </div>
            <div class="col-lg-12">
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
        </div>
        <div class="row">
            <div class="col-xl-9 cart-list">
                @if (count($products))
                    <form action="{{ routeArea('checkout.cart') }}" method="post" enctype="multipart/form-data"
                        role="form">
                        <div class="cart-info table-responsive shopping-summery">
                            <table class="table table-wishlist">
                                <thead>
                                    <tr class="main-heading">
                                        <th class="start pl-30" colspan="2">Sản phẩm</th>
                                        <th scope="col">Đơn giá</th>
                                        <th scope="col">Số lượng</th>
                                        <th scope="col" class="end">Tổng cộng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr class="pt-30">
                                            <td class="image product-thumbnail pt-40">
                                                <a href="{!! $product['url'] !!}">
                                                    <img src="{!! $product['image'] !!}" alt="{!! $product['name'] !!}"
                                                        title="{!! $product['name'] !!}"> </a>
                                            </td>
                                            <td class="product-des product-name">
                                                <h6 class="mb-5">
                                                    <a href="{!! $product['url'] !!}">{!! $product['name'] !!}</a>
                                                    @if (!$product['stock'])
                                                        <span class="stock">
                                                            <b style="color: #d81800;">(***)</b>
                                                        </span>
                                                    @endif
                                                </h6>
                                                <div class="cart-option">
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
                                                </div>
                                            </td>
                                            <td class="detail-info quantity" data-title="Số lượng">
                                                <input type="number" name="quantity[{{ $product['key'] }}]"
                                                    value="{{ $product['quantity'] }}" size="2" class="form-control">
                                                &nbsp;&nbsp;<input type="image" src="/client/images/applys.png"
                                                    alt="Cập nhật" title="Cập nhật">
                                                &nbsp;&nbsp;<a
                                                    href="{{ routeArea('checkout.cart', ['remove' => $product['key']]) }}"><i
                                                        class="fi-rs-trash text-danger"></i></a>
                                            </td>
                                            <td class="price" data-title="Đơn Giá">
                                                <h5 class="text-body">
                                                    {{ number_format($product['price'], 0, '', ',') . 'đ' }}
                                                </h5>
                                            </td>
                                            <td class="price" data-title="Tổng cộng">
                                                <h5 class="text-brand">
                                                    {{ number_format($product['total'], 0, '', ',') . 'đ' }}</h5>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="divider-2 mb-30"></div>
                        <div class="cart-action d-flex justify-content-between mb-30">
                            <a href="{{ url('/') }}" class="btn" title="Tiếp tục mua hàng">
                                <i class="fi-rs-arrow-left mr-10"></i>Tiếp tục mua hàng
                            </a>
                            <a href="{{ routeArea('checkout.index') }}" class="btn" title=" Tiến hành thanh toán">
                                Tiến hành thanh toán
                                <i class="fi-rs-sign-out ml-15"></i>
                            </a>
                        </div>
                    </form>
                @else
                    <div class="content page-not-found wrapper" style="text-align: left;">
                        Giỏ hàng của bạn đang trống!
                    </div>
                @endif
            </div>
            <div class="col-xl-3 receipt">
                @if (count($products))
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
                                    <h6>Hóa đơn của bạn</h6>
                                </div>
                                <div class="divider-2 mb-10"></div>
                                <div class="table-responsive">
                                    <table class="table custom no-border">
                                        <tbody id="total-data">
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
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-12">
                            <div class="pt-3 mt-3 border-top">
                                <form action="{{ routeArea('checkout.cart') }}" method="post"
                                    enctype="multipart/form-data" role="form" class="applyCoupon form-inline">
                                    <div id="couponForm">
                                        <div class="apply-coupon">
                                            <input type="text" name="coupon" id="coupon" class="form-control"
                                                placeholder="Nhập mã giảm giá (Coupon)" style="height: auto;">
                                            <button type="submit" class="btn btn-md">Sử dụng</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-xl-12">
                            <div class="py-3 my-3 border-top border-bottom">
                                <form action="{{ routeArea('checkout.cart') }}" method="post"
                                    enctype="multipart/form-data" role="form" class="applyVoucher form-inline">
                                    <div id="voucherForm">
                                        <div class="apply-coupon">
                                            <input type="text" name="voucher" id="voucher" class="form-control"
                                                placeholder="Nhập mã quà tặng (Voucher)" style="height: auto;">
                                            <button type="submit" class="btn btn-md">Sử dụng</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-xl-12 mt-3">
                            <div class="d-grid gap-2 col-12 mx-auto text-center">
                                <a class="btn btn-lg btn-block" href="{{ routeArea('checkout.index') }}">
                                    Tiến hành thanh toán
                                    <i class="fi-rs-sign-out ml-15"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @if (count($products))
        @include('web.checkout._choose_address')
    @endif
@stop
