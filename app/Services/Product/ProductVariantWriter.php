<?php

namespace App\Services\Product;

use App\Enums\OptionRole;
use App\Models\Entities\OptionValue;
use App\Models\Entities\Product;
use App\Models\Entities\ProductOption;
use App\Models\Entities\ProductStock;
use App\Models\Entities\ProductVariant;
use App\Models\Entities\ProductVariantAttribute;

class ProductVariantWriter
{
    public function sync(Product $product, array $options, array $variants): void
    {
        $this->syncOptions($product, $options);
        $this->syncVariants($product, $variants);
    }

    protected function syncOptions(Product $product, array $options): void
    {
        $keep = [];

        foreach ($options as $o) {
            $optionId = (int) ($o['option_id'] ?? 0);
            if (! $optionId) {
                continue;
            }

            $po = ProductOption::where('product_id', $product->id)
                ->where('option_id', $optionId)
                ->first() ?? new ProductOption();

            $po->product_id = $product->id;
            $po->option_id  = $optionId;
            $po->required   = ! empty($o['required']) ? 1 : 0;
            $po->value = OptionRole::fromInput($o['role'] ?? null)->isVariant()
                ? null
                : ($o['value'] ?? null);
            $po->save();

            $keep[] = $optionId;
        }

        ProductOption::where('product_id', $product->id)
            ->whereNotIn('option_id', $keep ?: [0])
            ->delete();
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $allValueIds = collect($variants)
            ->flatMap(fn ($v) => $v['option_value_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $valueToOption = OptionValue::whereIn('id', $allValueIds)
            ->pluck('option_id', 'id');

        $keepIds   = [];
        $hasDefault = false;

        foreach ($variants as $v) {
            $valueIds = array_map('intval', $v['option_value_ids'] ?? []);

            $optionToValue = [];
            foreach ($valueIds as $vid) {
                $oid = (int) ($valueToOption[$vid] ?? 0);
                if ($oid) {
                    $optionToValue[$oid] = $vid;
                }
            }
            $signature = ProductVariant::buildAttributeSignature($optionToValue);

            $variant = null;
            if (! empty($v['id'])) {
                $variant = ProductVariant::withTrashed()
                    ->where('product_id', $product->id)->find($v['id']);
            }
            if (! $variant) {
                $variant = ProductVariant::withTrashed()
                    ->where('product_id', $product->id)
                    ->where('attribute_signature', $signature)
                    ->first();
            }
            $variant ??= new ProductVariant();
            if ($variant->exists && $variant->trashed()) {
                $variant->restore();
            }

            $variant->product_id          = $product->id;
            $variant->price               = ($v['price'] ?? '') !== '' ? (float) $v['price'] : 0;
            $variant->sku                 = $v['sku'] ?? null;
            $variant->minimum             = max(1, (int) ($v['minimum'] ?? 1));
            $variant->sort_order          = (int) ($v['sort_order'] ?? 0);
            $variant->is_default          = ! empty($v['is_default']) ? 1 : 0;
            $variant->attribute_signature = $signature;
            $variant->save();

            $hasDefault = $hasDefault || $variant->is_default;
            $keepIds[]  = $variant->id;

            ProductVariantAttribute::where('product_variant_id', $variant->id)->delete();
            foreach ($optionToValue as $oid => $vid) {
                $attr = new ProductVariantAttribute();
                $attr->product_variant_id = $variant->id;
                $attr->option_id          = $oid;
                $attr->option_value_id    = $vid;
                $attr->save();
            }

            $stock = ProductStock::where('product_variant_id', $variant->id)->first()
                ?? new ProductStock();
            $stock->product_variant_id = $variant->id;
            $stock->on_hand            = (int) ($v['on_hand'] ?? 0);
            $stock->save();
        }

        if (! $hasDefault && ! empty($keepIds)) {
            ProductVariant::whereKey($keepIds[0])->update(['is_default' => 1]);
        }

        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('id', $keepIds ?: [0])
            ->delete();
    }
}
