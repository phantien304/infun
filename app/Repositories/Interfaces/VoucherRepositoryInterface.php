<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Voucher;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface VoucherRepositoryInterface extends BaseRepositoryInterface
{

    public function resolveVoucher(?string $code): array;

    public function findByCode(string $code): ?Voucher;

    public function findByCodes(array $codes): Collection;

    public function listForEmail(string $email): Collection;

    public function recordHistory(int $voucherId, int $orderId, ?int $userId, int $amount, int $status): void;

    public function historyForOrderByStatus(int $orderId, int $status): Collection;

    public function historyForOrderByStatuses(int $orderId, array $statuses): Collection;

    public function markHistoryStatus(array $ids, int $status): void;

    public function incrementRedeemed(int $voucherId, float $amount): void;

    public function decrementRedeemed(int $voucherId, float $amount): void;

    public function markFullyUsed(array $voucherIds, int $activeStatus, int $fullyUsedStatus): void;

    public function reactivateVouchers(array $voucherIds, int $fullyUsedStatus, int $activeStatus): void;

    public function flushCache(): void;
}
