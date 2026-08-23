<?php

namespace App\Repositories\Eloquent;

use App\Data\Affiliate\AffiliateConversionData;
use App\Enums\AffiliateConversionStatus;
use App\Models\Entities\AffiliateConversion;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AffiliateConversionRepository extends QueryableRepository implements AffiliateConversionRepositoryInterface
{
    public function model(): string
    {
        return AffiliateConversion::class;
    }

    public function recordConversion(AffiliateConversionData $data): void
    {
        if (! $data->valid()) {
            return;
        }

        $exists = $this->resetModel()->where('order_id', $data->orderId)->exists();
        if ($exists) {
            return;
        }

        AffiliateConversion::create([
            'affiliate_id'    => $data->affiliateId,
            'order_id'        => $data->orderId,
            'click_id'        => $data->clickId,
            'coupon_code'     => $data->couponCode,
            'order_total'     => $data->orderTotal,
            'commission'      => $data->commission,
            'commission_rate' => $data->rate,
            'status'          => AffiliateConversionStatus::Pending->value,
        ]);
    }

    public function approveForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->resetModel()
            ->where('order_id', $orderId)
            ->where('status', AffiliateConversionStatus::Pending->value)
            ->update([
                'status'      => AffiliateConversionStatus::Approved->value,
                'approved_at' => now(),
            ]);
    }

    public function rejectForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->resetModel()
            ->where('order_id', $orderId)
            ->whereIn('status', [
                AffiliateConversionStatus::Pending->value,
                AffiliateConversionStatus::Approved->value,
            ])
            ->update(['status' => AffiliateConversionStatus::Rejected->value]);
    }

    public function getListForAffiliate(int $affiliateId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getStatusTotals(int $affiliateId): array
    {
        $rows = $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->groupBy('status')
            ->get([
                'status',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(commission), 0) as commission'),
            ]);

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->status] = [
                'count'      => (int) $row->cnt,
                'commission' => (int) $row->commission,
            ];
        }

        return $totals;
    }

    public function countByDay(int $affiliateId, int $days): array
    {
        $rows = $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->where('created_at', '>=', now()->subDays(max(1, $days))->startOfDay())
            ->groupBy('d')
            ->orderBy('d')
            ->get([DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt')]);

        return $rows->pluck('cnt', 'd')->map(fn ($v) => (int) $v)->all();
    }

    // ===================== CMS (admin) =====================

    /**
     * Danh sách conversion cho CMS — màn đối soát chính. Lọc được theo KOL,
     * trạng thái, kỳ payout và khoảng ngày tạo.
     *
     * Eager load `affiliate.user` + `order`: không có 2 quan hệ này thì bảng
     * chỉ toàn id, admin phải tra chéo tay từng dòng.
     */
    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'order_id', 'order_total', 'commission', 'created_at', 'approved_at'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()->with(['affiliate.user', 'order']);

        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', (int) $request->input('affiliate_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('payout_id')) {
            $query->where('payout_id', (int) $request->input('payout_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from') . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // Keyword tra theo mã đơn hoặc mã coupon KOL — 2 thứ CSKH cầm trên tay
        // khi có khiếu nại "đơn này sao không tính hoa hồng".
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('coupon_code', 'like', '%' . $keyword . '%');
                if (ctype_digit($keyword)) {
                    $q->orWhere('order_id', (int) $keyword);
                }
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    /**
     * Hoa hồng ĐỦ ĐIỀU KIỆN chốt kỳ, gom theo KOL.
     *
     * Điều kiện: status = Approved (đơn đã giao thành công), CHƯA thuộc kỳ
     * nào (`payout_id IS NULL`), và đã qua hold_days tính từ `approved_at`
     * — khoảng chờ hết hạn đổi trả. Đơn Rejected sau khi đã trả tiền là mất
     * tiền thật, nên hold là chốt chặn duy nhất.
     *
     * @return Collection<int, object{affiliate_id: int, cnt: int, total: int}>
     */
    public function payableGroupedByAffiliate(string $cutoff): Collection
    {
        return $this->resetModel()->newQuery()
            ->where('status', AffiliateConversionStatus::Approved->value)
            ->whereNull('payout_id')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $cutoff)
            ->groupBy('affiliate_id')
            ->get([
                'affiliate_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(commission), 0) as total'),
            ]);
    }

    /** ID các conversion đủ điều kiện của MỘT KOL (cùng bộ lọc ở trên). */
    public function payableIdsForAffiliate(int $affiliateId, string $cutoff): array
    {
        return $this->resetModel()->newQuery()
            ->where('affiliate_id', $affiliateId)
            ->where('status', AffiliateConversionStatus::Approved->value)
            ->whereNull('payout_id')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Đưa conversion vào kỳ: Approved → Paid + gắn payout_id.
     *
     * Điều kiện `status = Approved AND payout_id IS NULL` lặp lại ở đây (đã
     * lọc lúc chọn id) là CỐ Ý: giữa lúc chọn và lúc ghi có thể có observer
     * reject đơn hoặc một lần chốt kỳ khác chạy song song. Câu UPDATE có
     * điều kiện là chốt chặn ở DB, không phải ở PHP.
     */
    public function attachPayout(array $conversionIds, int $payoutId): int
    {
        if (empty($conversionIds)) {
            return 0;
        }

        return $this->resetModel()->newQuery()
            ->whereIn('id', $conversionIds)
            ->where('status', AffiliateConversionStatus::Approved->value)
            ->whereNull('payout_id')
            ->update([
                'status'    => AffiliateConversionStatus::Paid->value,
                'payout_id' => $payoutId,
            ]);
    }

    /** Huỷ kỳ: Paid → Approved, gỡ payout_id (tiền chưa chuyển thật). */
    public function revertPayout(int $payoutId): int
    {
        return $this->resetModel()->newQuery()
            ->where('payout_id', $payoutId)
            ->where('status', AffiliateConversionStatus::Paid->value)
            ->update([
                'status'    => AffiliateConversionStatus::Approved->value,
                'payout_id' => null,
            ]);
    }

    /**
     * Tổng hoa hồng THỰC TẾ đã gắn vào một kỳ.
     *
     * Chốt kỳ tính lại `payout.amount` từ đây chứ không cộng dồn con số ước
     * lượng lúc chọn: giữa lúc chọn id và lúc UPDATE, một đơn có thể bị
     * observer reject → số dòng gắn được ít hơn dự kiến. Đọc lại sau khi ghi
     * là cách duy nhất để amount khớp đúng những gì thật sự nằm trong kỳ.
     */
    public function sumCommissionForPayout(int $payoutId): int
    {
        return (int) $this->resetModel()->newQuery()
            ->where('payout_id', $payoutId)
            ->sum('commission');
    }

    public function listForPayout(int $payoutId): Collection
    {
        return $this->resetModel()->newQuery()
            ->where('payout_id', $payoutId)
            ->with(['order'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Top KOL theo hoa hồng trong khoảng ngày. Bỏ conversion Rejected — xếp
     * hạng theo tiền đã hủy là xếp hạng sai người.
     *
     * @return Collection<int, object>
     */
    public function topAffiliates(string $from, string $to, int $limit = 10): Collection
    {
        return $this->resetModel()->newQuery()
            ->where('created_at', '>=', $from . ' 00:00:00')
            ->where('created_at', '<=', $to . ' 23:59:59')
            ->where('status', '!=', AffiliateConversionStatus::Rejected->value)
            ->groupBy('affiliate_id')
            ->orderByDesc(DB::raw('SUM(commission)'))
            ->limit(max(1, $limit))
            ->get([
                'affiliate_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(order_total), 0) as revenue'),
                DB::raw('COALESCE(SUM(commission), 0) as commission'),
            ]);
    }

    /** Tổng hợp theo trạng thái trong khoảng ngày — thẻ số liệu màn báo cáo. */
    public function summaryByStatus(string $from, string $to): array
    {
        $rows = $this->resetModel()->newQuery()
            ->where('created_at', '>=', $from . ' 00:00:00')
            ->where('created_at', '<=', $to . ' 23:59:59')
            ->groupBy('status')
            ->get([
                'status',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(commission), 0) as commission'),
                DB::raw('COALESCE(SUM(order_total), 0) as revenue'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->status] = [
                'count'      => (int) $row->cnt,
                'commission' => (int) $row->commission,
                'revenue'    => (int) $row->revenue,
            ];
        }

        return $out;
    }
}
