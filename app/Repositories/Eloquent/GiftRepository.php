<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Gift;
use App\Models\Entities\OrderGift;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\GiftRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GiftRepository extends QueryableRepository implements GiftRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Gift::class;
    }

    public function listActive(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('gift.cache.key_active'),
            fn () => $this->resetModel()
                ->newQuery()
                ->active()
                ->with([
                    'items.product.description',
                    'items.variant.description',
                    'triggerProducts',
                ])
                ->orderByDesc('sort_order')
                ->orderBy('id')
                ->get(),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('gift.cache.tag_root')],
        );
    }

    public function findActiveById(int $giftId): ?Gift
    {
        if ($giftId <= 0) {
            return null;
        }

        return $this->resetModel()
            ->newQuery()
            ->active()
            ->where('id', $giftId)
            ->with(['items.product.description', 'items.variant.description', 'triggerProducts'])
            ->first();
    }

    // === Write-side port từ GiftService (order_gift + used_count) ===

    public function insertOrderGiftItem(int $orderId, int $giftId, int $giftItemId, int $quantity): void
    {
        OrderGift::query()->insertOrIgnore([
            'order_id'     => $orderId,
            'gift_id'      => $giftId,
            'gift_item_id' => $giftItemId,
            'quantity'     => $quantity,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function incrementUsedCount(int $giftId, int $by = 1): void
    {
        DB::table('gift')->where('id', $giftId)->increment('used_count', $by);
    }

    public function decrementUsedCount(int $giftId): void
    {
        DB::table('gift')
            ->where('id', $giftId)
            ->where('used_count', '>=', 1)
            ->decrement('used_count');
    }

    public function orderGiftGiftIds(int $orderId): Collection
    {
        return OrderGift::query()->forOrder($orderId)->get(['gift_id']);
    }

    public function deleteOrderGifts(int $orderId): void
    {
        OrderGift::query()->forOrder($orderId)->delete();
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('gift.cache.tag_root')]);
    }
}
