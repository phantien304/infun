<?php

namespace App\Data\Cms;

use App\Enums\OptionRole;
use App\Models\Entities\Product;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public int $id,
        public ?string $model,
        public ?string $sku,
        public ?string $upc,
        public ?string $ean,
        public ?string $jan,
        public ?string $isbn,
        public ?string $mpn,
        public ?string $location,
        public ?string $image,
        public ?string $price,
        public ?string $quantity,
        public ?string $minimum,
        public ?string $badge,
        public ?int $manufacturer_id,
        public ?int $tax_class_id,
        public ?int $stock_status_id,
        public ?int $shipping,
        public ?int $subtract,
        public ?int $is_add_cart,
        public ?int $is_custom,
        public ?int $is_review,
        public ?string $date_available,
        public ?string $length,
        public ?string $width,
        public ?string $height,
        public ?int $length_class_id,
        public ?string $weight,
        public ?int $weight_class_id,
        public ?string $points,
        public ?string $sort_order,
        public ?string $link_sale,
        public mixed $link_sale_custom,
        public ?int $has_variants,
        public ?string $deleted_at,
        public array $product_descriptions,
        public array $product_categories,
        public array $product_filters,
        public array $product_related,
        public array $product_ingredients,
        public array $product_attributes,
        public array $product_images,
        public array $product_rewards,
        public array $product_options,
        public array $product_variants,
    ) {
    }

    public static function fromModel(Product $p): self
    {
        return new self(
            id: (int) $p->id,
            model: $p->model,
            sku: $p->sku,
            upc: $p->upc,
            ean: $p->ean,
            jan: $p->jan,
            isbn: $p->isbn,
            mpn: $p->mpn,
            location: $p->location,
            image: $p->image,
            price: ($v = $p->defaultVariant?->price) !== null ? (string) $v : null,
            quantity: ($q = $p->defaultVariant?->productStock?->on_hand) !== null ? (string) $q : null,
            minimum: ($m = $p->defaultVariant?->minimum) !== null ? (string) $m : null,
            badge: $p->badge,
            manufacturer_id: $p->manufacturer_id !== null ? (int) $p->manufacturer_id : null,
            tax_class_id: $p->tax_class_id !== null ? (int) $p->tax_class_id : null,
            stock_status_id: $p->stock_status_id !== null ? (int) $p->stock_status_id : null,
            shipping: $p->shipping !== null ? (int) $p->shipping : null,
            subtract: $p->subtract !== null ? (int) $p->subtract : null,
            is_add_cart: $p->is_add_cart !== null ? (int) $p->is_add_cart : null,
            is_custom: $p->is_custom !== null ? (int) $p->is_custom : null,
            is_review: $p->is_review !== null ? (int) $p->is_review : null,
            date_available: $p->date_available,
            length: $p->length !== null ? (string) $p->length : null,
            width: $p->width !== null ? (string) $p->width : null,
            height: $p->height !== null ? (string) $p->height : null,
            length_class_id: $p->length_class_id !== null ? (int) $p->length_class_id : null,
            weight: $p->weight !== null ? (string) $p->weight : null,
            weight_class_id: $p->weight_class_id !== null ? (int) $p->weight_class_id : null,
            points: $p->points !== null ? (string) $p->points : null,
            sort_order: $p->sort_order !== null ? (string) $p->sort_order : null,
            link_sale: $p->link_sale,
            link_sale_custom: $p->link_sale_custom, // raw (JSON string) — frontend tự parse
            has_variants: $p->has_variants !== null ? (int) $p->has_variants : null,
            deleted_at: $p->deleted_at?->toDateTimeString(),
            product_descriptions: $p->descriptions->map(fn ($d) => [
                'language_code'    => $d->language_code,
                'name'             => $d->name,
                'description'      => $d->description,
                'content'          => $d->content,
                'tag'              => $d->tag,
                'meta_title'       => $d->meta_title,
                'meta_description' => $d->meta_description,
                'meta_keyword'     => $d->meta_keyword,
            ])->values()->all(),
            product_categories: $p->productCategories->map(fn ($pc) => [
                'id'    => $pc->category_id,
                'title' => $pc->category?->description?->title,
            ])->values()->all(),
            product_filters: $p->productFilters->pluck('filter_value_id')->values()->all(),
            product_related: $p->productRelated->map(fn ($pr) => [
                'id'   => $pr->related_id,
                'name' => $pr->product?->description?->name,
            ])->values()->all(),
            product_ingredients: $p->productIngredients->map(fn ($pi) => [
                'id'   => $pi->ingredient_id,
                'name' => $pi->ingredient?->description?->name,
            ])->values()->all(),
            product_attributes: $p->productAttributes
                ->groupBy('attribute_id')
                ->map(fn ($rows, $attrId) => [
                    'attribute_id'      => (int) $attrId,
                    'product_attribute' => $rows->map(fn ($r) => [
                        'language_code' => $r->language_code,
                        'text'          => $r->text,
                    ])->values()->all(),
                ])->values()->all(),
            product_images: $p->productImages->map(fn ($img) => [
                'id'         => $img->id,
                'image'      => $img->image,
                'sort_order' => $img->sort_order,
            ])->values()->all(),
            product_rewards: $p->productRewards->map(fn ($r) => [
                'id'            => $r->id,
                'user_group_id' => $r->user_group_id,
                'points'        => $r->points,
            ])->values()->all(),
            product_options: (function () use ($p) {
                $variantGroups = $p->productVariants
                    ->flatMap(fn ($v) => $v->productVariantAttributes)
                    ->filter(fn ($attr) => $attr->optionValue)
                    ->groupBy('option_id')
                    ->map(function ($attrs, $optionId) {
                        $option = $attrs->first()->option;
                        if (!$option || !$option->isVariant()) {
                            return null;
                        }
                        $valueIds = $attrs->pluck('option_value_id')->unique()->values()->all();
                        if (empty($valueIds)) {
                            return null;
                        }
                        return [
                            'option_id'        => (int) $optionId,
                            'role'             => $option->role->value,
                            'required'         => true,
                            'value'            => null,
                            'option_value_ids' => $valueIds,
                        ];
                    })
                    ->filter()
                    ->values()->all();

                $customFieldGroups = $p->productOptions
                    ->filter(fn ($po) => $po->option?->isCustomField() ?? false)
                    ->map(fn ($po) => [
                        'option_id'        => $po->option_id,
                        'role'             => OptionRole::CustomField->value,
                        'required'         => $po->required,
                        'value'            => $po->value,
                        'option_value_ids' => [],
                    ])
                    ->values()->all();

                return array_merge($variantGroups, $customFieldGroups);
            })(),
            product_variants: $p->productVariants->map(function ($v) {
                $special = $v->productVariantSpecials
                    ?->firstWhere('user_group_id', 1);

                $defaultWarehouseId = (int) (getConfigDb('config_warehouse_id') ?: 1);
                $stocks   = $v->productStocks ?? collect();
                $onHand   = (int) ($stocks->sum('on_hand') ?? 0);
                $reserved = (int) ($stocks->sum('reserved') ?? 0);
                $policyStock = $stocks->firstWhere('warehouse_id', $defaultWarehouseId)
                    ?? $stocks->first();
                $policy = $policyStock?->inventory_policy instanceof \App\Enums\StockPolicy
                    ? $policyStock->inventory_policy->value
                    : (int) ($policyStock?->inventory_policy ?? 0);

                return [
                    'id'                 => $v->id,
                    'price'              => $v->price,
                    'regular_price'      => $v->regular_price !== null ? (string) $v->regular_price : '',
                    'sku'                => $v->sku,
                    'minimum'            => $v->minimum !== null ? (int) $v->minimum : 1,
                    'sort_order'         => $v->sort_order,
                    'is_default'         => $v->is_default,
                    'on_hand'            => $onHand,
                    'reserved'           => $reserved,
                    'available'          => max(0, $onHand - $reserved),
                    'inventory_policy'   => $policy,
                    'stocks' => $stocks->map(fn ($s) => [
                        'warehouse_id'     => (int) $s->warehouse_id,
                        'on_hand'          => (int) $s->on_hand,
                        'reserved'         => (int) $s->reserved,
                        'available'        => max(0, (int) $s->on_hand - (int) $s->reserved),
                        'inventory_policy' => $s->inventory_policy instanceof \App\Enums\StockPolicy
                            ? $s->inventory_policy->value
                            : (int) $s->inventory_policy,
                    ])->values()->all(),
                    'special_price'      => $special?->price !== null ? (string) $special->price : '',
                    'special_date_start' => $special?->date_start?->toDateString() ?? '',
                    'special_date_end'   => $special?->date_end?->toDateString() ?? '',
                    'option_value_ids'   => $v->productVariantAttributes
                        ->pluck('option_value_id')->values()->all(),
                    'discounts' => ($v->productVariantDiscounts ?? collect())->map(fn ($d) => [
                        'id'            => $d->id,
                        'user_group_id' => $d->user_group_id,
                        'quantity'      => $d->quantity,
                        'priority'      => $d->priority,
                        'price'         => (string) $d->price,
                        'date_start'    => $d->date_start?->toDateString() ?? '',
                        'date_end'      => $d->date_end?->toDateString() ?? '',
                    ])->values()->all(),
                ];
            })->values()->all(),
        );
    }
}
