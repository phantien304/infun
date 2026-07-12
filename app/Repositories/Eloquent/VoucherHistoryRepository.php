<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\VoucherHistory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\VoucherHistoryRepositoryInterface;
use Illuminate\Support\Collection;

class VoucherHistoryRepository extends QueryableRepository implements VoucherHistoryRepositoryInterface
{
    public function model(): string
    {
        return VoucherHistory::class;
    }

    public function record(int $voucherId, int $orderId, ?int $userId, int $amount, int $status): void
    {
        VoucherHistory::create([
            'voucher_id' => $voucherId,
            'order_id'   => $orderId,
            'user_id'    => $userId,
            'amount'     => $amount,
            'status'     => $status,
        ]);
    }

    public function forOrderByStatus(int $orderId, int $status): Collection
    {
        return VoucherHistory::query()
            ->forOrder($orderId)
            ->where('status', $status)
            ->get(['id', 'voucher_id', 'amount']);
    }

    public function forOrderByStatuses(int $orderId, array $statuses): Collection
    {
        return VoucherHistory::query()
            ->forOrder($orderId)
            ->whereIn('status', $statuses)
            ->get(['id', 'voucher_id', 'amount', 'status']);
    }

    public function markStatus(array $ids, int $status): void
    {
        if (empty($ids)) {
            return;
        }
        VoucherHistory::query()
            ->whereIn('id', $ids)
            ->update(['status' => $status, 'updated_at' => now()]);
    }
}
