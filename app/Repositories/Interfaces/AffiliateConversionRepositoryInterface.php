<?php

namespace App\Repositories\Interfaces;

use App\Data\Affiliate\AffiliateConversionData;
use App\Repositories\Base\BaseRepositoryInterface;

interface AffiliateConversionRepositoryInterface extends BaseRepositoryInterface
{
    /** Ghi conversion PENDING khi tạo đơn. Idempotent theo order_id (unique). */
    public function recordConversion(AffiliateConversionData $data): void;

    /** Đơn giao thành công → Approved (chỉ chuyển từ Pending). */
    public function approveForOrder(int $orderId): void;

    /** Đơn hủy → Rejected (từ Pending/Approved chưa Paid). */
    public function rejectForOrder(int $orderId): void;
}
