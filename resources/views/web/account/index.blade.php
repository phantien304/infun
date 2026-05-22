@extends('client.infunstudio.layouts.main_account')
@section('content')
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header">
                <h5>Tài khoản</h5>
            </div>
            <div class="card-body">
                <div class="row pt-50">
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('account.edit') }}" title="Thông tin tài khoản">
                            <i class="far fa-user fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center;">Thông tin tài khoản</p>
                        </a>
                    </div>
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('account.password') }}" title="Mật khẩu">
                            <i class="fas fa-key fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center;">Mật khẩu</p>
                        </a>
                    </div>
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('account.address') }}" title="Địa chỉ">
                            <i class="fas fa-map-marker-alt fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center; ">Địa chỉ</p>
                        </a>
                    </div>
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('account.wishlist') }}" title="Sản phẩm yêu thích">
                            <i class="far fa-star fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center;">Sản phẩm yêu thích</p>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-header">
                <h5>Đơn hàng</h5>
            </div>
            <div class="card-body">
                <div class="row pt-50">
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('checkout.cart') }}" title="Giỏ hàng">
                            <i class="fas fa-cart-arrow-down fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center;">Giỏ hàng</p>
                        </a>
                    </div>
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('account.orders') }}" title="Lịch sử mua hàng">
                            <i class="far fa-address-book fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center; ">Lịch sử mua hàng</p>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-header">
                <h5>Tin khuyến mãi</h5>
            </div>
            <div class="card-body">
                <div class="row pt-50">
                    <div class="col-xl-3 text-center mb-50">
                        <a href="{{ route('account.newsletter') }}" title="Đăng ký nhận tin khuyến mãi">
                            <i class="fas fa-newspaper fa-4x"></i>
                            <p style="margin-top: 20px; text-align:center;">Đăng ký nhận tin khuyến mãi</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
