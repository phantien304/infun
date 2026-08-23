<?php

namespace App\Repositories\Eloquent;

use App\Enums\AffiliatePayoutStatus;
use App\Models\Entities\AffiliatePayout;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliatePayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Kỳ chi trả hoa hồng (`affiliate_payout`) — Phase 5 AFFILIATE-PLAN.md.
 *
 * Một kỳ = (affiliate, period 'YYYY-MM'), UNIQUE ở DB. Vòng đời:
 * chốt kỳ → Pending → kế toán chuyển khoản xong → Paid; chốt nhầm → Cancelled
 * (conversion quay lại Approved để vào kỳ sau).
 */
class AffiliatePayoutRepository extends QueryableRepository implements AffiliatePayoutRepositoryInterface
{
    public function model(): string
    {
        return AffiliatePayout::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'period', 'amount', 'created_at', 'paid_at'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()->with(['affiliate.user']);

        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', (int) $request->input('affiliate_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('period')) {
            $query->where('period', trim((string) $request->input('period')));
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?AffiliatePayout
    {
        return $this->resetModel()->with(['affiliate.user'])->find($id);
    }

    public function findByAffiliateAndPeriod(int $affiliateId, string $period): ?AffiliatePayout
    {
        return $this->resetModel()->newQuery()
            ->where('affiliate_id', $affiliateId)
            ->where('period', $period)
            ->first();
    }

    /** Toàn bộ kỳ của một period, kèm KOL + user — nguồn cho file CSV chuyển khoản. */
    public function listByPeriod(string $period): Collection
    {
        return $this->resetModel()->newQuery()
            ->with(['affiliate.user'])
            ->where('period', $period)
            ->orderBy('id')
            ->get();
    }

    public function createPayout(int $affiliateId, string $period, int $amount): AffiliatePayout
    {
        return AffiliatePayout::create([
            'affiliate_id' => $affiliateId,
            'period'       => $period,
            'amount'       => $amount,
            'status'       => AffiliatePayoutStatus::Pending->value,
        ]);
    }

    public function addAmount(AffiliatePayout $payout, int $amount): AffiliatePayout
    {
        $payout->amount = (int) $payout->amount + $amount;
        $payout->save();

        return $payout;
    }

    /**
     * Đánh dấu đã chuyển khoản. Không đụng tới conversion: chúng đã ở trạng
     * thái Paid từ lúc chốt kỳ — `payout.status` nói về việc TIỀN đã rời tài
     * khoản công ty hay chưa, hai chuyện khác nhau.
     */
    public function markPaid(AffiliatePayout $payout, ?string $note): AffiliatePayout
    {
        $payout->status = AffiliatePayoutStatus::Paid->value;
        $payout->paid_at = now();
        if ($note !== null) {
            $payout->note = $note;
        }
        $payout->save();

        return $payout->load(['affiliate.user']);
    }

    public function cancel(AffiliatePayout $payout, ?string $note): AffiliatePayout
    {
        $payout->status = AffiliatePayoutStatus::Cancelled->value;
        $payout->amount = 0;
        if ($note !== null) {
            $payout->note = $note;
        }
        $payout->save();

        return $payout->load(['affiliate.user']);
    }
}
