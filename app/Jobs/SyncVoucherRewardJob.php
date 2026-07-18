<?php

namespace App\Jobs;

use App\Models\Entities\Orders;
use App\Services\Voucher\VoucherRewardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVoucherRewardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $orderId,
    ) {
        $this->afterCommit = true;
    }

    public function handle(VoucherRewardService $service): void
    {
        $order = Orders::query()->find($this->orderId);
        if (! $order) {
            return;
        }

        $statusId = (int) $order->order_status_id;

        $completeIds = array_map('intval', (array) getConfigDb('order_complete_status_all', []));
        if (in_array($statusId, $completeIds, true)) {
            $service->grantForOrder($order);

            return;
        }

        $cancelId = (int) getConfigDb('order_cancel_status_id');
        if ($cancelId > 0 && $statusId === $cancelId) {
            $service->revokeForOrder($order);
        }
    }
}
