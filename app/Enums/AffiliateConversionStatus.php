<?php

namespace App\Enums;

/**
 * Vòng đời hoa hồng (row `affiliate_conversion`):
 * Pending khi tạo đơn → Approved khi giao thành công (qua observer, sau đó
 * chờ hold_days mới đủ điều kiện payout) → Paid khi chốt kỳ.
 * Đơn hủy/refund → Rejected.
 */
enum AffiliateConversionStatus: int
{
    case Pending = 0;
    case Approved = 1;
    case Rejected = 2;
    case Paid = 3;

    /** Đủ điều kiện gom vào kỳ payout (sau khi qua hold_days). */
    public function isPayable(): bool
    {
        return $this === self::Approved;
    }
}
