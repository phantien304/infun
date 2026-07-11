@extends('web::layouts.main_account')
@section('content')
    @php
        $rate = (float) getConfigDb('config_affiliate_commission_rate', 5);
        $cookieDays = (int) getConfigDb('config_affiliate_cookie_days', 30);
        $minPayout = (int) getConfigDb('config_affiliate_min_payout', 200000);
        $holdDays = (int) getConfigDb('config_affiliate_hold_days', 7);
    @endphp
    <div class="col-xl-9 account">
        <div class="card mb-30">
            <div class="card-header">
                <h3>Đăng ký tiếp thị liên kết</h3>
            </div>
            <div class="card-body">
                <div class="row mb-30">
                    <div class="col-md-4 text-center mb-3">
                        <div class="fw-bold" style="font-size: 1.5rem;">{{ rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') }}%</div>
                        <div class="text-gray-500 text-sm">hoa hồng mỗi đơn giao thành công</div>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <div class="fw-bold" style="font-size: 1.5rem;">{{ $cookieDays }} ngày</div>
                        <div class="text-gray-500 text-sm">ghi nhận đơn từ lượt click của bạn</div>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <div class="fw-bold" style="font-size: 1.5rem;">{{ number_format($minPayout, 0, ',', '.') }}đ</div>
                        <div class="text-gray-500 text-sm">ngưỡng thanh toán tối thiểu mỗi kỳ</div>
                    </div>
                </div>

                <form action="{{ route('account.affiliate.register') }}" method="post">
                    @csrf
                    <h5 class="mb-20">Thông tin nhận hoa hồng (chuyển khoản)</h5>
                    <div class="row mb-30">
                        <label for="inputBankName" class="col-sm-3 col-form-label">Ngân hàng</label>
                        <div class="col-sm-9">
                            <input type="text" name="bank_name" id="inputBankName" placeholder="VD: Vietcombank"
                                value="{{ old('bank_name') }}"
                                class="form-control @if ($errors->has('bank_name')) is-invalid @endif">
                            @if ($errors->has('bank_name'))
                                <div class="invalid-feedback">{{ $errors->first('bank_name') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputBankAccount" class="col-sm-3 col-form-label">Số tài khoản</label>
                        <div class="col-sm-9">
                            <input type="text" name="bank_account" id="inputBankAccount" placeholder="Số tài khoản"
                                value="{{ old('bank_account') }}" inputmode="numeric"
                                class="form-control @if ($errors->has('bank_account')) is-invalid @endif">
                            @if ($errors->has('bank_account'))
                                <div class="invalid-feedback">{{ $errors->first('bank_account') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputBankHolder" class="col-sm-3 col-form-label">Chủ tài khoản</label>
                        <div class="col-sm-9">
                            <input type="text" name="bank_holder" id="inputBankHolder" placeholder="Tên chủ tài khoản (in hoa, không dấu)"
                                value="{{ old('bank_holder') }}"
                                class="form-control @if ($errors->has('bank_holder')) is-invalid @endif">
                            @if ($errors->has('bank_holder'))
                                <div class="invalid-feedback">{{ $errors->first('bank_holder') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputZaloPay" class="col-sm-3 col-form-label">SĐT ZaloPay <span class="text-gray-400">(tùy chọn)</span></label>
                        <div class="col-sm-9">
                            <input type="text" name="zalopay_phone" id="inputZaloPay" placeholder="Số điện thoại liên kết ZaloPay"
                                value="{{ old('zalopay_phone') }}" inputmode="tel"
                                class="form-control @if ($errors->has('zalopay_phone')) is-invalid @endif">
                            @if ($errors->has('zalopay_phone'))
                                <div class="invalid-feedback">{{ $errors->first('zalopay_phone') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-30 p-3 bg-light rounded text-sm" style="max-height: 180px; overflow-y: auto;">
                        <strong>Điều khoản chương trình (tóm tắt):</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            <li>Hoa hồng tính trên giá trị đơn sau giảm giá, trước phí vận chuyển; ghi nhận khi đơn <strong>giao thành công</strong> và giữ {{ $holdDays }} ngày chờ hết hạn đổi trả.</li>
                            <li>Đơn hủy / hoàn trả không được tính hoa hồng.</li>
                            <li>Không tự mua hàng qua link/mã của chính mình; không spam link, không chạy quảng cáo trùng thương hiệu.</li>
                            <li>Thanh toán chuyển khoản theo kỳ hàng tháng khi hoa hồng đạt tối thiểu {{ number_format($minPayout, 0, ',', '.') }}đ.</li>
                            <li>Chúng tôi có quyền tạm khóa tài khoản affiliate nếu phát hiện gian lận.</li>
                        </ul>
                    </div>
                    <div class="form-check mb-30">
                        <input class="form-check-input @if ($errors->has('agree')) is-invalid @endif" type="checkbox"
                            name="agree" id="checkAgree" value="1" @if (old('agree')) checked @endif>
                        <label class="form-check-label" for="checkAgree">
                            Tôi đã đọc và đồng ý với điều khoản chương trình tiếp thị liên kết
                        </label>
                        @if ($errors->has('agree'))
                            <div class="invalid-feedback">{{ $errors->first('agree') }}</div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-md">Đăng ký tham gia</button>
                </form>
            </div>
        </div>
    </div>
@stop
