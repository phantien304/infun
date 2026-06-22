<?php

namespace App\Http\Resources;

use App\Models\Entities\Option;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Map Product (schema cluster mới) → đúng shape mà infun_cms form đọc.
 * -----------------------------------------------------------
 * Controller show() phải eager-load:
 *   descriptions, productCategories.category.description, productFilters,
 *   productRelated.product.description, productIngredients.ingredient.description,
 *   productAttributes, productImages, productRewards, productDiscounts,
 *   productOptions.option, productVariants.productVariantAttributes,
 *   productVariants.productStocks, defaultVariant
 *
 * LƯU Ý: best-effort theo contract của frontend (chưa test trên DB thật) —
 * tên cột/relation có thể cần chỉnh theo schema khi chạy.
 * -----------------------------------------------------------
 */
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $p = $this->resource;

        return [
            // ----- field phẳng -----
            'id'                => (int) $p->id,
            'model'             => $p->model,
            'sku'               => $p->sku,
            'upc'               => $p->upc,
            'ean'               => $p->ean,
            'jan'               => $p->jan,
            'isbn'              => $p->isbn,
            'mpn'               => $p->mpn,
            'location'          => $p->location,
            'image'             => $p->image,
            'price'             => $p->price, // accessor: defaultVariant price
            'quantity'          => $p->quantity,
            'minimum'          => $p->minimum,
            'badge'             => $p->badge,
            'manufacturer_id'   => $p->manufacturer_id,
            'tax_class_id'      => $p->tax_class_id,
            'stock_status_id'   => $p->stock_status_id,
            'shipping'          => $p->shipping,
            'subtract'          => $p->subtract,
            'is_add_cart'       => $p->is_add_cart,
            'is_custom'         => $p->is_custom,
            'is_review'         => $p->is_review,
            'date_available'    => $p->date_available,
            'length'            => $p->length,
            'width'             => $p->width,
            'height'            => $p->height,
            'length_class_id'   => $p->length_class_id,
            'weight'            => $p->weight,
            'weight_class_id'   => $p->weight_class_id,
            'points'            => $p->points,
            'sort_order'        => $p->sort_order,
            'link_sale'         => $p->link_sale,
            'link_sale_custom'  => $p->link_sale_custom,
            'has_variants'      => $p->has_variants,
            'deleted_at'        => $p->deleted_at,

            // ----- mô tả đa ngữ -----
            'product_descriptions' => $p->descriptions->map(fn ($d) => [
                'language_code'    => $d->language_code,
                'name'             => $d->name,
                'description'      => $d->description,
                'content'          => $d->content,
                'tag'              => $d->tag,
                'meta_title'       => $d->meta_title,
                'meta_description' => $d->meta_description,
                'meta_keyword'     => $d->meta_keyword,
            ])->values(),

            // ----- categories: {id, title} -----
            'product_categories' => $p->productCategories->map(fn ($pc) => [
                'id'    => $pc->category_id,
                'title' => $pc->category?->description?->title,
            ])->values(),

            // ----- filters: [filter_value_id] -----
            'product_filters' => $p->productFilters->pluck('filter_value_id')->values(),

            // ----- related: {id, name} -----
            'product_related' => $p->productRelated->map(fn ($pr) => [
                'id'   => $pr->related_id,
                'name' => $pr->product?->description?->name,
            ])->values(),

            // ----- ingredients: {id, name} -----
            'product_ingredients' => $p->productIngredients->map(fn ($pi) => [
                'id'   => $pi->ingredient_id,
                'name' => $pi->ingredient?->description?->name,
            ])->values(),

            // ----- attributes: gom theo attribute_id, text theo ngôn ngữ -----
            'product_attributes' => $p->productAttributes
                ->groupBy('attribute_id')
                ->map(fn ($rows, $attrId) => [
                    'attribute_id'      => (int) $attrId,
                    'product_attribute' => $rows->map(fn ($r) => [
                        'language_code' => $r->language_code,
                        'text'          => $r->text,
                    ])->values(),
                ])->values(),

            // ----- images: {id, image, sort_order} -----
            'product_images' => $p->productImages->map(fn ($img) => [
                'id'         => $img->id,
                'image'      => $img->image,
                'sort_order' => $img->sort_order,
            ])->values(),

            // ----- rewards: {id, user_group_id, points} -----
            'product_rewards' => $p->productRewards->map(fn ($r) => [
                'id'            => $r->id,
                'user_group_id' => $r->user_group_id,
                'points'        => $r->points,
            ])->values(),

            // ----- discounts -----
            'product_discounts' => $p->productDiscounts->map(fn ($d) => [
                'id'            => $d->id,
                'user_group_id' => $d->user_group_id,
                'quantity'      => $d->quantity,
                'priority'      => $d->priority,
                'price'         => $d->price,
                'date_start'    => $d->date_start,
                'date_end'      => $d->date_end,
            ])->values(),

            // ----- options (khai báo) -----
            'product_options' => $p->productOptions->map(function ($po) use ($p) {
                $role = $po->option?->role ?? Option::ROLE_CUSTOM_FIELD;
                // option_value_ids của variant-role lấy từ pivot variant.
                $valueIds = [];
                if ($role === Option::ROLE_VARIANT) {
                    $valueIds = $p->productVariants
                        ->flatMap(fn ($v) => $v->productVariantAttributes)
                        ->where('option_id', $po->option_id)
                        ->pluck('option_value_id')
                        ->unique()->values()->all();
                }
                return [
                    'option_id'        => $po->option_id,
                    'role'             => $role,
                    'required'         => $po->required,
                    'value'            => $po->value,
                    'option_value_ids' => $valueIds,
                ];
            })->values(),

            // ----- variants: {id, price, sku, sort_order, is_default, on_hand, option_value_ids[]} -----
            'product_variants' => $p->productVariants->map(fn ($v) => [
                'id'               => $v->id,
                'price'            => $v->price,
                'sku'              => $v->sku,
                'sort_order'       => $v->sort_order,
                'is_default'       => $v->is_default,
                'on_hand'          => $v->productStocks?->sum('on_hand') ?? 0,
                'option_value_ids' => $v->productVariantAttributes
                    ->pluck('option_value_id')->values()->all(),
            ])->values(),
        ];
    }
}
