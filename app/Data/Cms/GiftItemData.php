<?php

namespace App\Data\Cms;

use App\Models\Entities\GiftItem;
use Spatie\LaravelData\Data;

/**
 * Một món quà trong chương trình (`gift_item`).
 * `product_variant_id` NULL khi SP simple không cần chọn biến thể.
 */
class GiftItemData extends Data
{
    public function __construct(
        public ?int $id,
        public int $product_id,
        public ?string $product_name,
        public ?int $product_variant_id,
        public ?string $product_variant_name,
        public int $quantity,
        public int $sort_order,
    ) {
    }

    public static function fromModel(GiftItem $item): self
    {
        return new self(
            id: $item->id === null ? null : (int) $item->id,
            product_id: (int) $item->product_id,
            product_name: $item->relationLoaded('product')
                ? $item->product?->description?->name
                : null,
            product_variant_id: $item->product_variant_id === null ? null : (int) $item->product_variant_id,
            product_variant_name: $item->relationLoaded('variant')
                ? $item->variant?->description?->name
                : null,
            quantity: (int) $item->quantity,
            sort_order: (int) $item->sort_order,
        );
    }
}
