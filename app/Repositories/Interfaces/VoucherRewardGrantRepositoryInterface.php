<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\VoucherRewardGrant;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface VoucherRewardGrantRepositoryInterface extends BaseRepositoryInterface
{
    public function rewardGrantExists(int $ruleId, int $orderId): bool;

    public function countRewardGrantsForUser(int $ruleId, ?int $userId, string $email): int;

    public function createRewardGrant(array $data): VoucherRewardGrant;

    public function rewardGrantsForOrder(int $orderId): Collection;

    public function flushCache(): void;
}
