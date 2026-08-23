<?php

namespace App\Repositories\Interfaces;

use App\Data\Affiliate\AffiliateConversionData;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface AffiliateConversionRepositoryInterface extends BaseRepositoryInterface
{
    /** Ghi conversion PENDING khi tạo đơn. Idempotent theo order_id (unique). */
    public function recordConversion(AffiliateConversionData $data): void;

    /** Đơn giao thành công → Approved (chỉ chuyển từ Pending). */
    public function approveForOrder(int $orderId): void;

    /** Đơn hủy → Rejected (từ Pending/Approved chưa Paid). */
    public function rejectForOrder(int $orderId): void;

    /** Bảng conversion cho dashboard KOL (Phase 4), mới nhất trước. */
    public function getListForAffiliate(int $affiliateId, int $perPage = 20): LengthAwarePaginator;

    /** [status => ['count' => n, 'commission' => sum]] — stat cards dashboard. */
    public function getStatusTotals(int $affiliateId): array;

    /** ['Y-m-d' => count] conversion $days ngày gần nhất (mọi status) — chart. */
    public function countByDay(int $affiliateId, int $days): array;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    /**
     * Hoa hồng đủ điều kiện chốt kỳ (Approved, chưa có payout, approved_at
     * <= cutoff), gom theo KOL.
     */
    public function payableGroupedByAffiliate(string $cutoff): Collection;

    public function payableIdsForAffiliate(int $affiliateId, string $cutoff): array;

    /** Approved → Paid + gắn payout_id. Trả số dòng thực sự đổi. */
    public function attachPayout(array $conversionIds, int $payoutId): int;

    /** Huỷ kỳ: Paid → Approved, gỡ payout_id. */
    public function revertPayout(int $payoutId): int;

    /** Tổng hoa hồng thực tế đã gắn vào kỳ — nguồn duy nhất cho payout.amount. */
    public function sumCommissionForPayout(int $payoutId): int;

    public function listForPayout(int $payoutId): Collection;

    public function topAffiliates(string $from, string $to, int $limit = 10): Collection;

    public function summaryByStatus(string $from, string $to): array;
}
