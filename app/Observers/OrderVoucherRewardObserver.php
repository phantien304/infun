<?php

namespace App\Observers;

use App\Jobs\SyncVoucherRewardJob;
use App\Models\Entities\Orders;

class OrderVoucherRewardObserver
{
    public function updated(Orders $order): void
    {
        if (! $order->wasChanged('order_status_id')) {
            return;
        }

        $statusId = (int) $order->order_status_id;

        $completeIds = array_map('intval', (array) getConfigDb('order_complete_status_all', []));
        $cancelId = (int) getConfigDb('order_cancel_status_id');

        if (in_array($statusId, $completeIds, true) || ($cancelId > 0 && $statusId === $cancelId)) {
            dispatch(new SyncVoucherRewardJob((int) $order->id));
        }
    }
}
