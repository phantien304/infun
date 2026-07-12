<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface VoucherHistoryRepositoryInterface extends BaseRepositoryInterface
{
    public function record(int $voucherId, int $orderId, ?int $userId, int $amount, int $status): void;

    public function forOrderByStatus(int $orderId, int $status): Collection;

    public function forOrderByStatuses(int $orderId, array $statuses): Collection;

    public function markStatus(array $ids, int $status): void;
}
