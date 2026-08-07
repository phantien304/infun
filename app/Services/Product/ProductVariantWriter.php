<?php

namespace App\Services\Product;

use App\Enums\OptionRole;
use App\Enums\StockPolicy;
use App\Models\Entities\OptionValue;
use App\Models\Entities\Product;
use App\Models\Entities\ProductOption;
use App\Models\Entities\ProductStock;
use App\Models\Entities\ProductVariant;
use App\Models\Entities\ProductVariantAttribute;
use App\Models\Entities\ProductVariantDiscount;
use App\Models\Entities\ProductVariantSpecial;
use App\Services\Stock\WarehouseService;

class ProductVariantWriter
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {
    }

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

            if (OptionRole::fromInput($o['role'] ?? null)->isVariant()) {
                continue;
            }

            $po = ProductOption::withTrashed()
                ->where('product_id', $product->id)
                ->where('option_id', $optionId)
                ->first() ?? new ProductOption();

            $po->product_id = $product->id;
            $po->option_id  = $optionId;
            $po->required   = ! empty($o['required']) ? 1 : 0;
            $po->value = $o['value'] ?? null;
            $po->price = ($o['price'] ?? '') !== '' ? (float) $o['price'] : 0;
            $po->deleted_at = null;
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
            $variant->regular_price       = ($v['regular_price'] ?? '') !== '' ? (float) $v['regular_price'] : null;
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

            $this->syncVariantStocks($variant, $v);

            $this->syncVariantSpecial($product, $variant, $v);
            $this->syncVariantDiscounts($product, $variant, $v);
        }

        if (! $hasDefault && ! empty($keepIds)) {
            ProductVariant::whereKey($keepIds[0])->update(['is_default' => 1]);
        }

        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('id', $keepIds ?: [0])
            ->delete();
    }

    protected function syncVariantStocks(ProductVariant $variant, array $v): void
    {
        $rows = $v['stocks'] ?? [];
        if (empty($rows)) {
            $rows = [[
                'warehouse_id'     => $this->warehouseService->defaultId(),
                'on_hand'          => $v['on_hand'] ?? 0,
                'inventory_policy' => $v['inventory_policy'] ?? 0,
            ]];
        }

        $keepWarehouseIds = [];

        foreach ($rows as $row) {
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            if (! $warehouseId) {
                continue;
            }

            $stock = ProductStock::where('product_variant_id', $variant->id)
                ->where('warehouse_id', $warehouseId)
                ->first() ?? new ProductStock();

            $policy = StockPolicy::fromInput($row['inventory_policy'] ?? 0);
            $stock->product_variant_id = $variant->id;
            $stock->warehouse_id       = $warehouseId;
            $stock->on_hand            = max(0, (int) ($row['on_hand'] ?? 0));
            $stock->inventory_policy   = $policy;
            $stock->subtract           = $policy !== StockPolicy::Untracked;
            $stock->save();

            $keepWarehouseIds[] = $warehouseId;
        }

        ProductStock::where('product_variant_id', $variant->id)
            ->whereNotIn('warehouse_id', $keepWarehouseIds ?: [0])
            ->delete();
    }

    protected function syncVariantSpecial(Product $product, ProductVariant $variant, array $v): void
    {
        $userGroupId = 1;

        $special = ProductVariantSpecial::withTrashed()
            ->where('product_variant_id', $variant->id)
            ->where('user_group_id', $userGroupId)
            ->first();

        $price = $v['special_price'] ?? '';
        if ($price === '' || $price === null) {
            $special?->delete();

            return;
        }

        $special ??= new ProductVariantSpecial();
        if ($special->exists && $special->trashed()) {
            $special->restore();
        }

        $start = $v['special_date_start'] ?? '';
        $end   = $v['special_date_end'] ?? '';

        $special->product_variant_id = $variant->id;
        $special->product_id         = $product->id;
        $special->user_group_id      = $userGroupId;
        $special->priority           = (int) ($v['special_priority'] ?? 0);
        $special->price              = (float) $price;
        $special->date_start         = $start !== '' ? $start . ' 00:00:00' : null;
        $special->date_end           = $end !== '' ? $end . ' 23:59:59' : null;
        $special->save();
    }

    protected function syncVariantDiscounts(Product $product, ProductVariant $variant, array $v): void
    {
        $rows = $v['discounts'] ?? [];
        $keepIds = [];

        foreach ($rows as $row) {
            $price = $row['price'] ?? '';
            if ($price === '' || $price === null) {
                continue;
            }

            $discount = null;
            if (! empty($row['id'])) {
                $discount = ProductVariantDiscount::withTrashed()
                    ->where('product_variant_id', $variant->id)
                    ->find($row['id']);
            }
            $discount ??= new ProductVariantDiscount();
            if ($discount->exists && $discount->trashed()) {
                $discount->restore();
            }

            $start = $row['date_start'] ?? '';
            $end   = $row['date_end'] ?? '';

            $discount->product_variant_id = $variant->id;
            $discount->product_id         = $product->id;
            $discount->user_group_id      = (int) ($row['user_group_id'] ?? 1);
            $discount->quantity           = max(1, (int) ($row['quantity'] ?? 1));
            $discount->priority           = (int) ($row['priority'] ?? 0);
            $discount->price              = (float) $price;
            $discount->date_start         = $start !== '' ? $start . ' 00:00:00' : null;
            $discount->date_end           = $end !== '' ? $end . ' 23:59:59' : null;
            $discount->save();

            $keepIds[] = $discount->id;
        }

        ProductVariantDiscount::where('product_variant_id', $variant->id)
            ->whereNotIn('id', $keepIds ?: [0])
            ->delete();
    }
}
