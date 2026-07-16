<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Gift;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface GiftRepositoryInterface extends BaseRepositoryInterface
{
    public function listActive(): Collection;

    public function findActiveById(int $giftId): ?Gift;

    public function incrementUsedCount(int $giftId, int $by = 1): int;

    public function decrementUsedCount(int $giftId): void;

    public function flushCache(): void;
}
