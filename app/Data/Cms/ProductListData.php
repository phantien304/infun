<?php

namespace App\Data\Cms;

use App\Models\Entities\Product;
use Spatie\LaravelData\Data;

/**
 * DTO Product cho TRANG LIST CMS (bản nhẹ — khác ProductData chi tiết).
 * Quy ước app/Data/Cms: property snake_case = JSON wire = cột DB.
 */
class ProductListData extends Data
{
    public function __construct(
        public int $id,
        public ?string $image,
        public ?string $name,
        public ?string $model,
        public ?string $badge,
        public ?float $price,
        public ?int $quantity,
        public ?string $deleted_at,
        public ?array $product_draft,
    ) {
    }

    public static function fromModel(Product $p): self
    {
        return new self(
            id: (int) $p->id,
            image: $p->image,
            name: $p->name, // cột join từ product_description
            model: $p->model,
            badge: $p->badge,
            // accessor getPriceAttribute không fire (Base+Compoships) → đọc thẳng default variant.
            price: (float) ($p->defaultVariant?->price ?? 0),
            quantity: $p->quantity !== null ? (int) $p->quantity : null,
            deleted_at: $p->deleted_at?->toDateTimeString(),
            product_draft: $p->productDraft ? ['id' => $p->productDraft->id] : null,
        );
    }
}
