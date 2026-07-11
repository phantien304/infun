<?php

namespace App\Observers;

use App\Models\Entities\Orders;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;

/**
 * Vòng đời hoa hồng affiliate theo trạng thái đơn — mirror OrderRewardObserver
 * (mọi flow đổi status đều qua orderRepo->upsertOrder = Eloquent save):
 *  - status ∈ `order_complete_status_all` (giao thành công) → conversion
 *    Pending → APPROVED + approved_at (hold_days tính từ đây, áp ở Phase 5
 *    khi chốt kỳ payout).
 *  - status = `order_cancel_status_id` → Pending/Approved (chưa Paid)
 *    → REJECTED.
 * Idempotent: repo chỉ update đúng status nguồn nên gọi lặp vô hại.
 */
class OrderAffiliateObserver
{
    public function updated(Orders $order): void
    {
        if (! $order->wasChanged('order_status_id')) {
            return;
        }

        $statusId = (int) $order->order_status_id;
        $orderId = (int) $order->id;
        $repo = app(AffiliateConversionRepositoryInterface::class);

        $completeIds = array_map('intval', (array) getConfigDb('order_complete_status_all', []));
        if (in_array($statusId, $completeIds, true)) {
            $repo->approveForOrder($orderId);

            return;
        }

        $cancelId = (int) getConfigDb('order_cancel_status_id');
        if ($cancelId > 0 && $statusId === $cancelId) {
            $repo->rejectForOrder($orderId);
        }
    }
}
