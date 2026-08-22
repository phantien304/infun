<?php

namespace App\Data\Cms;

use App\Models\Entities\Gift;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO chương trình quà tặng — TÍNH NĂNG MỚI, mt219 KHÔNG có.
 *
 * trigger_type: 1=min_subtotal (đơn từ X), 2=buy_specific_product
 *   (mua SP trong `gift_trigger_product`)  — App\Enums\GiftTriggerType.
 * pick_type: 0=auto, 1=pick_1_of_n, 2=pick_up_to_n (pick_limit là trần)
 *   — App\Enums\GiftPickType.
 *
 * `used_count` CHỈ HIỂN THỊ — GiftRepository increment/decrement theo đơn.
 */
class GiftData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $trigger_type,
        public ?float $min_subtotal,
        public int $pick_type,
        public ?int $pick_limit,
        public ?int $uses_total,
        public int $used_count,
        public ?string $date_start,
        public ?string $date_end,
        public bool $is_active,
        public int $sort_order,
        public ?string $badge,
        public ?string $deleted_at,
        public int $gift_item_count,
        public Collection $gift_items,
        public Collection $gift_trigger_products,
    ) {
    }

    public static function fromModel(Gift $gift): self
    {
        return new self(
            id: (int) $gift->id,
            name: (string) $gift->name,
            description: $gift->description,
            trigger_type: (int) $gift->trigger_type,
            min_subtotal: $gift->min_subtotal === null ? null : (float) $gift->min_subtotal,
            pick_type: (int) $gift->pick_type,
            pick_limit: $gift->pick_limit === null ? null : (int) $gift->pick_limit,
            uses_total: $gift->uses_total === null ? null : (int) $gift->uses_total,
            used_count: (int) $gift->used_count,
            date_start: $gift->date_start?->toDateTimeString(),
            date_end: $gift->date_end?->toDateTimeString(),
            is_active: (bool) $gift->is_active,
            sort_order: (int) $gift->sort_order,
            badge: $gift->badge,
            deleted_at: $gift->deleted_at?->toDateTimeString(),
            // `items_count` chỉ có khi list query dùng withCount('items');
            // ở màn chi tiết thì đếm trên quan hệ đã eager load.
            gift_item_count: (int) ($gift->items_count
                ?? ($gift->relationLoaded('items') ? $gift->items->count() : 0)),
            gift_items: $gift->relationLoaded('items')
                ? GiftItemData::collect($gift->items, Collection::class)
                : collect(),
            gift_trigger_products: $gift->relationLoaded('triggerProducts')
                ? $gift->triggerProducts->map(fn ($tp) => [
                    'id'   => (int) $tp->product_id,
                    'name' => $tp->relationLoaded('product')
                        ? $tp->product?->description?->name
                        : null,
                ])->values()
                : collect(),
        );
    }
}
