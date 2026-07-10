<?php

namespace App\Enums;

/**
 * Trạng thái kỳ chi trả (row `affiliate_payout`): chốt kỳ → Pending,
 * chuyển khoản xong → Paid. Cancelled khi hủy kỳ (conversion quay về Approved).
 */
enum AffiliatePayoutStatus: int
{
    case Pending = 0;
    case Paid = 1;
    case Cancelled = 2;
}
