<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface OrderGiftRepositoryInterface extends BaseRepositoryInterface
{
    public function insertItem(int $orderId, int $giftId, int $giftItemId, int $quantity): void;

    public function giftIdsForOrder(int $orderId): Collection;

    public function deleteForOrder(int $orderId): void;
}
