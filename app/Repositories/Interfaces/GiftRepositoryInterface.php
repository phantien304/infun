<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Gift;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface GiftRepositoryInterface extends BaseRepositoryInterface
{
    public function listActive(): Collection;

    public function findActiveById(int $giftId): ?Gift;

    public function insertOrderGiftItem(int $orderId, int $giftId, int $giftItemId, int $quantity): void;

    public function incrementUsedCount(int $giftId, int $by = 1): void;

    public function decrementUsedCount(int $giftId): void;

    public function orderGiftGiftIds(int $orderId): Collection;

    public function deleteOrderGifts(int $orderId): void;

    public function flushCache(): void;
}
