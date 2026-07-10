<?php

namespace App\Enums;

/**
 * Trạng thái tài khoản affiliate: đăng ký → Pending, admin duyệt (hoặc auto
 * theo config_affiliate_auto_approve) → Active, vi phạm → Suspended.
 * Chỉ Active mới được ghi click / conversion.
 */
enum AffiliateStatus: int
{
    case Pending = 0;
    case Active = 1;
    case Suspended = 2;

    public function canTrack(): bool
    {
        return $this === self::Active;
    }
}
