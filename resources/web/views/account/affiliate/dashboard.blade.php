@extends('web::layouts.main_account')
@section('content')
    @php
        use App\Enums\AffiliateConversionStatus;
        $fmt = fn ($v) => number_format((int) $v, 0, ',', '.');
        $holdDays = (int) getConfigDb('config_affiliate_hold_days', 7);
        $minPayout = (int) getConfigDb('config_affiliate_min_payout', 200000);
        $refUrl = url('/') . '?ref=' . $affiliate->code;
    @endphp
    <div class="col-xl-9 account">
        <div class="card mb-30">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                <h3>Tiếp thị liên kết</h3>
                <a href="{{ route('account.affiliate.links') }}" class="btn btn-sm">
                    <i class="fas fa-link mr-5"></i>Tạo link giới thiệu
                </a>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center flex-wrap mb-30">
                    <span class="text-gray-500 me-2">Mã giới thiệu:</span>
                    <code class="me-2" style="font-size: 1rem;">{{ $affiliate->code }}</code>
                    <button type="button" class="btn btn-sm js-copy" data-copy="{{ $refUrl }}"
                        title="Copy link giới thiệu trang chủ">
                        <i class="far fa-copy mr-5"></i>Copy link giới thiệu
                    </button>
                </div>

                <div class="row mb-30">
                    <div class="col-6 col-md-3 mb-3">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fw-bold" style="font-size: 1.3rem;">{{ $fmt($stats['clicks_total']) }}</div>
                            <div class="text-gray-500 text-sm">Lượt click</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fw-bold text-secondary" style="font-size: 1.3rem;">{{ $fmt($stats['pending_commission']) }}đ</div>
                            <div class="text-gray-500 text-sm">Chờ giao hàng ({{ $fmt($stats['pending_count']) }} đơn)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fw-bold text-success" style="font-size: 1.3rem;">{{ $fmt($stats['approved_commission']) }}đ</div>
                            <div class="text-gray-500 text-sm">Đã duyệt, chờ chi ({{ $fmt($stats['approved_count']) }} đơn)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fw-bold text-primary" style="font-size: 1.3rem;">{{ $fmt($stats['paid_commission']) }}đ</div>
                            <div class="text-gray-500 text-sm">Đã thanh toán ({{ $fmt($stats['paid_count']) }} đơn)</div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mb-30">
                    Hoa hồng duyệt khi đơn giao thành công, giữ {{ $holdDays }} ngày chờ hết hạn đổi trả;
                    chi trả theo kỳ tháng khi đạt tối thiểu {{ $fmt($minPayout) }}đ.
                </p>

                <div class="mb-30" style="position: relative; height: 260px;">
                    <canvas id="affiliateChart"></canvas>
                </div>

                @if ($coupons->count())
                    <h5 class="mb-15">Mã giảm giá dành riêng cho bạn</h5>
                    <p class="text-xs text-gray-400">Khách nhập các mã này khi đặt hàng — đơn tự động tính hoa hồng cho bạn, không cần click link.</p>
                    <div class="mb-30">
                        @foreach ($coupons as $coupon)
                            <span class="badge bg-warning text-dark me-2 mb-2 js-copy" role="button"
                                data-copy="{{ $coupon->code }}" title="Bấm để copy"
                                style="font-size: 0.95rem;">{{ $coupon->code }} <i class="far fa-copy"></i></span>
                        @endforeach
                    </div>
                @endif

                <h5 class="mb-15">Đơn hàng ghi nhận</h5>
                @if ($entities->count())
                    <div class="table-responsive">
                        <table class="table custom">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Đơn hàng</th>
                                    <th>Nguồn</th>
                                    <th class="text-end">Giá trị đơn</th>
                                    <th class="text-end">Hoa hồng</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entities as $row)
                                    @php $status = AffiliateConversionStatus::tryFrom((int) $row->status); @endphp
                                    <tr>
                                        <td class="text-nowrap">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>#{{ $row->order_id }}</td>
                                        <td class="text-gray-500 text-sm">
                                            {{ $row->coupon_code ? 'Mã ' . $row->coupon_code : 'Link giới thiệu' }}
                                        </td>
                                        <td class="text-end">{{ $fmt($row->order_total) }}đ</td>
                                        <td class="text-end fw-bold">
                                            {{ $fmt($row->commission) }}đ
                                            <span class="text-xs text-gray-400">({{ rtrim(rtrim(number_format((float) $row->commission_rate, 2, '.', ''), '0'), '.') }}%)</span>
                                        </td>
                                        <td>
                                            @if ($status === AffiliateConversionStatus::Pending)
                                                <span class="badge bg-secondary">Chờ giao hàng</span>
                                            @elseif ($status === AffiliateConversionStatus::Approved)
                                                <span class="badge bg-success">Đã duyệt</span>
                                            @elseif ($status === AffiliateConversionStatus::Paid)
                                                <span class="badge bg-primary">Đã thanh toán</span>
                                            @else
                                                <span class="badge bg-danger">Đơn hủy</span>
                                            @endif
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
                    <div class="text-center text-gray-400 py-4">
                        <p>Chưa có đơn hàng nào được ghi nhận.</p>
                        <p class="text-xs">Chia sẻ link giới thiệu hoặc mã giảm giá của bạn để bắt đầu nhận hoa hồng.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop
@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var chartData = @json($stats['chart']);
            var ctx = document.getElementById('affiliateChart');
            if (ctx && window.Chart) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Lượt click',
                            data: chartData.clicks,
                            borderColor: '#3BB77E',
                            backgroundColor: 'rgba(59,183,126,.12)',
                            fill: true,
                            tension: .3,
                            pointRadius: 2
                        }, {
                            label: 'Đơn ghi nhận',
                            data: chartData.conversions,
                            borderColor: '#FDC040',
                            backgroundColor: 'rgba(253,192,64,.12)',
                            fill: true,
                            tension: .3,
                            pointRadius: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            document.addEventListener('click', function (e) {
                var el = e.target.closest('.js-copy');
                if (!el) return;
                var text = el.getAttribute('data-copy') || '';
                var done = function () {
                    var old = el.innerHTML;
                    el.innerHTML = '<i class="fas fa-check mr-5"></i>Đã copy';
                    setTimeout(function () { el.innerHTML = old; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    done();
                }
            });
        })();
    </script>
@stop
