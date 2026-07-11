<?php

namespace App\Repositories\Interfaces;

use App\Data\Affiliate\AffiliateConversionData;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
