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

    public function incrementRedeemed(int $voucherId, float $amount): int;

    public function decrementRedeemed(int $voucherId, float $amount): void;

    public function markFullyUsed(array $voucherIds, int $activeStatus, int $fullyUsedStatus): void;

    public function reactivateVouchers(array $voucherIds, int $fullyUsedStatus, int $activeStatus): void;

    public function createVoucher(array $data): Voucher;

    public function revokeUnused(array $voucherIds, int $activeStatus, int $revokedStatus): int;

    public function reactivateRevoked(array $voucherIds, int $revokedStatus, int $activeStatus): int;

    public function flushCache(): void;
}
