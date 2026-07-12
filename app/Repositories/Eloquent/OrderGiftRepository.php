<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\OrderGift;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OrderGiftRepositoryInterface;
use Illuminate\Support\Collection;

class OrderGiftRepository extends QueryableRepository implements OrderGiftRepositoryInterface
{
    public function model(): string
    {
        return OrderGift::class;
    }

    public function insertItem(int $orderId, int $giftId, int $giftItemId, int $quantity): void
    {
        $this->resetModel()->insertOrIgnore([
            'order_id'     => $orderId,
            'gift_id'      => $giftId,
            'gift_item_id' => $giftItemId,
            'quantity'     => $quantity,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function giftIdsForOrder(int $orderId): Collection
    {
        return $this->resetModel()->forOrder($orderId)->get(['gift_id']);
    }

    public function deleteForOrder(int $orderId): void
    {
        $this->resetModel()->forOrder($orderId)->delete();
    }
}
