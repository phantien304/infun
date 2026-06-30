<?php

namespace App\Services\Product;

use App\Models\Entities\Option;
use App\Models\Entities\OptionValue;
use App\Models\Entities\Product;
use App\Models\Entities\ProductOption;
use App\Models\Entities\ProductStock;
use App\Models\Entities\ProductVariant;
use App\Models\Entities\ProductVariantAttribute;

/**
 * Ghi phần OPTION + VARIANT (cluster) của product — phần phức tạp nhất, tách
 * khỏi ProductWriteService để service không thành god class.
 * -----------------------------------------------------------
 * Payload (từ infun_cms Option tab):
 *   product_options : [{ option_id, role, required, value, option_value_ids[] }]
 *   product_variants: [{ id, price, sku, sort_order, is_default, on_hand, option_value_ids[] }]
 *
 * Quy tắc schema mới (xem CLAUDE.md):
 *   - product_option: khai báo. variant-role → value NULL; custom_field → value.
 *   - product_variant: 1 tổ hợp. attribute_signature = ProductVariant::buildAttributeSignature
 *     ([option_id => option_value_id]) — NGUỒN SỰ THẬT, không tự viết md5.
 *   - product_variant_attribute: pivot (product_variant_id, option_id, option_value_id).
 *   - product_stock: on_hand tách riêng.
 *   - Ghi variant qua Eloquent để observer cập nhật min/max_variant_price.
 *
 * Best-effort — chưa test trên DB; verify cột (variant.sku, stock.warehouse_id) khi chạy.
 * -----------------------------------------------------------
 */
class ProductVariantWriter
{
    public function sync(Product $product, array $options, array $variants): void
    {
        $this->syncOptions($product, $options);
        $this->syncVariants($product, $variants);
    }

    /** Khai báo product_options theo role; xoá option không còn trong payload. */
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
            // variant-role → value NULL (giá trị nằm ở pivot variant); custom_field → default.
            $po->value = ((int) ($o['role'] ?? 0) === Option::ROLE_VARIANT)
                ? null
                : ($o['value'] ?? null);
            $po->save();

            $keep[] = $optionId;
        }

        ProductOption::where('product_id', $product->id)
            ->whereNotIn('option_id', $keep ?: [0])
            ->delete();
    }

    /** Upsert variants + pivot attribute + stock; xoá variant không còn. */
    protected function syncVariants(Product $product, array $variants): void
    {
        // Map option_value_id -> option_id để derive (chống drift).
        $allValueIds = collect($variants)
            ->flatMap(fn ($v) => $v['option_value_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $valueToOption = OptionValue::whereIn('id', $allValueIds)
            ->pluck('option_id', 'id'); // [value_id => option_id]

        $keepIds   = [];
        $hasDefault = false;

        foreach ($variants as $v) {
            $valueIds = array_map('intval', $v['option_value_ids'] ?? []);

            // [option_id => option_value_id]
            $optionToValue = [];
            foreach ($valueIds as $vid) {
                $oid = (int) ($valueToOption[$vid] ?? 0);
                if ($oid) {
                    $optionToValue[$oid] = $vid;
                }
            }
            $signature = ProductVariant::buildAttributeSignature($optionToValue);

            // Tìm theo id (>0) → theo signature (gồm cả trashed để tránh đụng UNIQUE).
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
            $variant->sort_order          = (int) ($v['sort_order'] ?? 0);
            $variant->is_default          = ! empty($v['is_default']) ? 1 : 0;
            $variant->attribute_signature = $signature;
            $variant->save();

            $hasDefault = $hasDefault || $variant->is_default;
            $keepIds[]  = $variant->id;

            // pivot product_variant_attribute — rebuild.
            ProductVariantAttribute::where('product_variant_id', $variant->id)->delete();
            foreach ($optionToValue as $oid => $vid) {
                $attr = new ProductVariantAttribute();
                $attr->product_variant_id = $variant->id;
                $attr->option_id          = $oid;
                $attr->option_value_id    = $vid;
                $attr->save();
            }

            // tồn kho.
            $stock = ProductStock::where('product_variant_id', $variant->id)->first()
                ?? new ProductStock();
            $stock->product_variant_id = $variant->id;
            $stock->on_hand            = (int) ($v['on_hand'] ?? 0);
            $stock->save();
        }

        // Bảo đảm đúng 1 default.
        if (! $hasDefault && ! empty($keepIds)) {
            ProductVariant::whereKey($keepIds[0])->update(['is_default' => 1]);
        }

        // Xoá mềm variant không còn trong payload.
        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('id', $keepIds ?: [0])
            ->delete();
    }
}
