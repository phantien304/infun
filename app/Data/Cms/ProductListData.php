<?php

namespace App\Data\Cms;

use App\Models\Entities\Product;
use Spatie\LaravelData\Data;

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
        public bool $has_variants,
        public int $variant_count,
        public ?string $deleted_at,
        public ?array $product_draft,
    ) {
    }

    public static function fromModel(Product $p): self
    {
        return new self(
            id: (int) $p->id,
            image: $p->image,
            name: $p->name,
            model: $p->model,
            badge: $p->badge,
            price: (float) ($p->defaultVariant?->price ?? 0),
            quantity: (int) ($p->agg_quantity ?? 0),
            has_variants: (bool) $p->has_variants,
            variant_count: (int) ($p->variant_count ?? 0),
            deleted_at: $p->deleted_at?->toDateTimeString(),
            product_draft: $p->productDraft ? ['id' => $p->productDraft->id] : null,
        );
    }
}
