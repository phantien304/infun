<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\AffiliatePayout;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface AffiliatePayoutRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?AffiliatePayout;

    public function findByAffiliateAndPeriod(int $affiliateId, string $period): ?AffiliatePayout;

    /** Toàn bộ kỳ của một period kèm KOL — nguồn cho file CSV chuyển khoản. */
    public function listByPeriod(string $period): Collection;

    /** Tạo kỳ chi trả mới (status Pending). */
    public function createPayout(int $affiliateId, string $period, int $amount): AffiliatePayout;

    /** Cộng thêm tiền vào kỳ Pending đã có (chốt kỳ lần 2 trong cùng tháng). */
    public function addAmount(AffiliatePayout $payout, int $amount): AffiliatePayout;

    public function markPaid(AffiliatePayout $payout, ?string $note): AffiliatePayout;

    public function cancel(AffiliatePayout $payout, ?string $note): AffiliatePayout;
}
