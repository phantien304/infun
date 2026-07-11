@extends('web::layouts.main_account')
@section('content')
    @php
        use App\Enums\AffiliateStatus;
        $status = AffiliateStatus::tryFrom((int) $affiliate->status);
    @endphp
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Tiếp thị liên kết</h3>
            </div>
            <div class="card-body">
                @if ($status === AffiliateStatus::Pending)
                    <div class="text-center py-5">
                        <i class="far fa-clock text-warning" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3">Hồ sơ đang chờ duyệt</h5>
                        <p class="text-gray-500">
                            Bạn đã đăng ký ngày {{ $affiliate->created_at?->format('d/m/Y') }}.
                            Chúng tôi sẽ duyệt trong thời gian sớm nhất — kết quả gửi qua email.
                        </p>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-ban text-danger" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3">Tài khoản affiliate tạm khóa</h5>
                        <p class="text-gray-500">
                            Link giới thiệu của bạn tạm ngừng ghi nhận. Vui lòng liên hệ
                            <a href="{{ route('contact.index') }}" class="underline">bộ phận hỗ trợ</a> để biết thêm chi tiết.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop
