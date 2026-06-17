<?php

namespace App\Data\Output;

use App\Data\Concerns\HasThumbnail;
use App\Models\Entities\GiftItem;
use Spatie\LaravelData\Data;

/**
 * 1 SP/variant cụ thể được tặng kèm. Pre-compute label hiển thị (productName
 * + variantName) trong DTO — blade chỉ render.
 */
class GiftItemDTO extends Data
{
    use HasThumbnail;

    public function __construct(
        public int $id,
        public int $giftId,
        public int $productId,
        public ?int $productVariantId,
        public string $productName,
        public ?string $variantName,
        public ?string $image,
        public int $quantity,
        public int $sortOrder,
    ) {}

    public static function fromModel(GiftItem $item): self
    {
        $product = $item->product;
        $variant = $item->variant;
        $productName = (string) ($product?->description?->name ?? '');
        $variantName = null;
        if ($variant) {
            // Variant description name (if defined) hoặc fallback ID label.
            $variantName = (string) ($variant->description?->name ?? "Variant #{$variant->id}");
        }

        return new self(
            id:                (int) $item->id,
            giftId:            (int) $item->gift_id,
            productId:         (int) $item->product_id,
            productVariantId:  $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
            productName:       $productName,
            variantName:       $variantName,
            image:             $product?->image,
            quantity:          (int) $item->quantity,
            sortOrder:         (int) $item->sort_order,
        );
    }
}
