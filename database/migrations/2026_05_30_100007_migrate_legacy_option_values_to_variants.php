<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy dữ liệu cũ → schema variant mới. KHÔNG drop bảng cũ — giữ song song
 * để rollback / dual-read. Drop ở migration riêng sau khi confirm.
 *
 * Quy tắc chuyển:
 *  - Mỗi row product_option_value_2 (kể cả "placeholder" có option_value_2_id
 *    NULL) → 1 product_variant.
 *  - price_delta cũ (price + price_prefix) → quy về absolute:
 *      product_variant.price = product.price + signed(price, price_prefix)
 *    với signed = price khi prefix '+', -price khi '-', 0 khi NULL/khác.
 *  - quantity → product_stock.on_hand (warehouse_id mặc định = 1).
 *  - Pivot product_variant_attribute: 1 row cho option_value_1, 1 row cho
 *    option_value_2 (nếu khác NULL). Bỏ qua placeholder.
 *  - attribute_signature: tính MD5(option_value_id list sort theo option_id).
 *
 * Vì cần JOIN sang bảng option để biết option_id từ option_value, ta query
 * option_value → option_id, build pivot từng row.
 *
 * Không idempotent — chạy đúng 1 lần trên DB sạch chưa có variant. Migration
 * nào re-run được cần `truncate` trước; để an toàn ở đây check empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bail-out nếu đã có variant — tránh ghi đè dữ liệu thật.
        if (DB::table('product_variant')->exists()) {
            return;
        }
        // Bail-out nếu bảng cũ không tồn tại — không có gì để copy.
        if (! Schema::hasTable('product_option_value') || ! Schema::hasTable('product_option_value_2')) {
            return;
        }

        $defaultWarehouse = 1;

        // Chỉ option có type variant mới được build vào product_variant.
        // Các type custom field (text/date/file/phone/email/...) đi đường khác
        // (lưu vào order_item.custom_fields khi checkout) — KHÔNG tạo SKU riêng.
        $variantTypes = ['radio', 'checkbox', 'select', 'image'];

        DB::transaction(function () use ($defaultWarehouse, $variantTypes) {
            // Cache option_id theo option_value_id — lọc bỏ option soft-deleted
            // và option có type không phải variant. Sau bước này, option_value
            // nào không có entry trong $optionByValue đồng nghĩa "không vào pivot".
            $optionByValue = DB::table('option_value as ov')
                ->join('option as o', 'o.id', '=', 'ov.option_id')
                ->whereIn('o.type', $variantTypes)
                ->whereNull('o.deleted_at')
                ->pluck('ov.option_id', 'ov.id');

            // Cache base price theo product_id.
            $basePriceByProduct = DB::table('product')->pluck('price', 'id');

            $rows = DB::table('product_option_value as pov')
                ->leftJoin('product_option_value_2 as pov2', 'pov2.product_option_value_id', '=', 'pov.id')
                ->leftJoin('product_option as po', 'po.id', '=', 'pov.product_option_id')
                ->select([
                    'pov.id as pov_id',
                    'pov.product_id as product_id',
                    'pov.option_value_1_id',
                    'pov.image',
                    'pov2.id as pov2_id',
                    'pov2.option_value_2_id',
                    'pov2.quantity',
                    'pov2.subtract',
                    'pov2.price',
                    'pov2.price_prefix',
                    'pov2.points',
                    'pov2.points_prefix',
                    'pov2.weight',
                    'pov2.weight_prefix',
                ])
                ->orderBy('pov.id')
                ->cursor();

            foreach ($rows as $row) {
                $productId = (int) $row->product_id;
                $basePrice = (float) ($basePriceByProduct[$productId] ?? 0);

                // Resolve signed delta cho từng cột (price/points/weight).
                $priceFinal = $basePrice + $this->signed($row->price, $row->price_prefix);
                $pointsFinal = (int) $this->signed($row->points, $row->points_prefix);
                $weightFinal = $row->weight === null
                    ? null
                    : (float) $this->signed($row->weight, $row->weight_prefix);

                // Build danh sách (option_id, option_value_id) tham gia tổ hợp.
                $attributes = [];
                if ($row->option_value_1_id && isset($optionByValue[$row->option_value_1_id])) {
                    $attributes[(int) $optionByValue[$row->option_value_1_id]] = (int) $row->option_value_1_id;
                }
                if ($row->option_value_2_id && isset($optionByValue[$row->option_value_2_id])) {
                    $attributes[(int) $optionByValue[$row->option_value_2_id]] = (int) $row->option_value_2_id;
                }
                if (empty($attributes)) {
                    // Row hoàn toàn rỗng — skip, không tạo variant ma.
                    continue;
                }

                $signature = $this->signature($attributes);

                // Dedupe: cùng product + signature → bỏ qua row trùng.
                $existing = DB::table('product_variant')
                    ->where('product_id', $productId)
                    ->where('attribute_signature', $signature)
                    ->value('id');

                if ($existing) {
                    // Có thể có nhiều row pov2 cộng dồn quantity cho cùng tổ hợp.
                    // Quyết định: cộng dồn on_hand, giữ price của row đầu tiên.
                    DB::table('product_stock')
                        ->where('product_variant_id', $existing)
                        ->where('warehouse_id', $defaultWarehouse)
                        ->increment('on_hand', (int) ($row->quantity ?? 0));
                    continue;
                }

                $variantId = DB::table('product_variant')->insertGetId([
                    'product_id'          => $productId,
                    'sku'                 => null,
                    'attribute_signature' => $signature,
                    'price'               => $priceFinal,
                    'points'              => $pointsFinal,
                    'weight'              => $weightFinal,
                    'image'               => $row->image,
                    'is_default'          => 0,
                    'sort_order'          => 0,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // Pivot
                foreach ($attributes as $optionId => $optionValueId) {
                    DB::table('product_variant_attribute')->insert([
                        'product_variant_id'      => $variantId,
                        'option_id'       => $optionId,
                        'option_value_id' => $optionValueId,
                    ]);
                }

                // Stock
                DB::table('product_stock')->insert([
                    'product_variant_id'   => $variantId,
                    'warehouse_id' => $defaultWarehouse,
                    'on_hand'      => (int) ($row->quantity ?? 0),
                    'reserved'     => 0,
                    'subtract'     => (int) ($row->subtract ?? 1),
                    'version'      => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // Khởi tạo stock movement audit cho lần seed
                DB::table('stock_movement')->insert([
                    'product_variant_id'      => $variantId,
                    'warehouse_id'    => $defaultWarehouse,
                    'type'            => 'receive',
                    'quantity_change' => (int) ($row->quantity ?? 0),
                    'on_hand_after'   => (int) ($row->quantity ?? 0),
                    'reference_type'  => 'migration',
                    'reference_id'    => null,
                    'user_id'         => null,
                    'note'            => 'Migrated from legacy product_option_value_2',
                    'created_at'      => now(),
                ]);
            }

            // Đánh dấu product có variant
            DB::statement('
                UPDATE product
                SET has_variants = 1
                WHERE id IN (SELECT product_id FROM product_variant)
            ');

            // Backfill min/max variant price
            DB::statement('
                UPDATE product p
                LEFT JOIN (
                    SELECT product_id, MIN(price) AS mn, MAX(price) AS mx
                    FROM product_variant
                    GROUP BY product_id
                ) v ON v.product_id = p.id
                SET p.min_variant_price = v.mn,
                    p.max_variant_price = v.mx
            ');

            // Seed product_option_definition từ pivot
            DB::statement('
                INSERT IGNORE INTO product_option_definition (product_id, option_id, is_required, sort_order)
                SELECT DISTINCT pv.product_id, pva.option_id, 1, 0
                FROM product_variant_attribute pva
                INNER JOIN product_variant pv ON pv.id = pva.product_variant_id
            ');
        });
    }

    public function down(): void
    {
        DB::table('stock_movement')->where('reference_type', 'migration')->delete();
        DB::table('product_stock')->delete();
        DB::table('product_variant_attribute')->delete();
        DB::table('product_variant_description')->delete();
        DB::table('product_variant')->delete();
        DB::table('product_option_definition')->delete();
        DB::table('product')->update([
            'has_variants'      => 0,
            'min_variant_price' => null,
            'max_variant_price' => null,
        ]);
    }

    /**
     * Quy đổi (value, prefix) cũ thành signed float.
     *  '+' →  value
     *  '-' → -value
     *  còn lại → 0
     */
    private function signed($value, ?string $prefix): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $v = (float) $value;
        return match ($prefix) {
            '+' => $v,
            '-' => -$v,
            default => 0.0,
        };
    }

    /**
     * MD5 các option_value_id sort theo option_id (key). Đảm bảo tổ hợp
     * (Size=M, Color=Red) và (Color=Red, Size=M) cho cùng signature.
     */
    private function signature(array $optionToValue): string
    {
        ksort($optionToValue);
        $parts = [];
        foreach ($optionToValue as $optionId => $valueId) {
            $parts[] = $optionId.':'.$valueId;
        }
        return md5(implode('|', $parts));
    }
};
