@extends('web::layouts.main_account')
@section('content')
    @php
        use App\Enums\RewardStatus;
        use App\Enums\RewardTransactionType;
    @endphp
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3>Điểm thưởng</h3>
                <span class="badge bg-warning text-dark" style="font-size: 1rem;">
                    Khả dụng: {{ number_format($balance, 0, '', ',') }} điểm
                </span>
            </div>
            <div class="card-body">
                @if ($entities->count())
                    <div class="table-responsive">
                        <table class="table custom">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Nội dung</th>
                                    <th class="text-end">Điểm</th>
                                    <th>Trạng thái</th>
                                    <th>Hết hạn</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entities as $row)
                                    @php
                                        $points = (int) $row->points;
                                        $status = RewardStatus::tryFrom((int) $row->status);
                                        $type = RewardTransactionType::tryFrom((int) $row->transaction_type);
                                        $typeLabel = match ($type) {
                                            RewardTransactionType::OnOrder => 'Tích từ đơn hàng',
                                            RewardTransactionType::Redeem => 'Dùng điểm',
                                            RewardTransactionType::RedeemRefund => 'Hoàn điểm (đơn hủy)',
                                            default => 'Khác',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <div>{{ $typeLabel }}</div>
                                            @if ($row->order_id)
                                                <a href="{{ route('account.detailOrder', $row->order_id) }}"
                                                    class="text-xs text-gray-500 underline">
                                                    {{ $row->description }}
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-500">{{ $row->description }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold {{ $points >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $points >= 0 ? '+' : '' }}{{ number_format($points, 0, '', ',') }}
                                        </td>
                                        <td>
                                            @if ($status === RewardStatus::Pending)
                                                <span class="badge bg-secondary">Chờ giao hàng</span>
                                            @elseif ($status === RewardStatus::Revoked)
                                                <span class="badge bg-danger">Đã thu hồi</span>
                                            @elseif ($row->expires_at && $row->expires_at->isPast())
                                                <span class="badge bg-dark">Hết hạn</span>
                                            @else
                                                <span class="badge bg-success">Khả dụng</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-gray-500">
                                            {{ $row->expires_at ? $row->expires_at->format('d/m/Y') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                        <nav aria-label="Phân trang">
                            {!! $entities->links('web::share.structure._paging', ['removeKey' => ['per_page']]) !!}
                        </nav>
                    </div>
                @else
                    <div class="text-center text-gray-400 py-5">
                        <i class="fi-rs-star" style="font-size: 2.5rem;"></i>
                        <p class="mt-2">Bạn chưa có giao dịch điểm nào</p>
                        <p class="text-xs">Mua hàng để tích điểm — điểm khả dụng khi đơn giao thành công</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop
