<?php

namespace App\Console\Commands;

use App\Enums\StockMovementType;
use App\Enums\StockPolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fill 1 default variant + product_stock cho mọi product CHƯA có variant.
 *
 * Sao phải có?
 * -----------
 * Migration `2026_06_11_000003_unify_simple_product_stock` đã thiết lập
 * Shopify-style: MỌI product (kể cả simple) đều có ≥1 product_variant +
 * 1 product_stock. CartService/CreateOrderService chỉ đọc product_stock,
 * KHÔNG còn rẽ nhánh `if (variant) ... else product.quantity`.
 *
 * Migration đó chỉ chạy 1 lần với data ban đầu. Khi seed thêm product
 * mới qua `products:seed` → các product mới rơi vào trạng thái "không
 * có variant" → bị invisible khỏi stock pipeline. Command này backfill
 * lại cho data seed.
 *
 * Khác `variants:seed` ở chỗ:
 *  - variants:seed sinh 2-3 variant Color×Size có thật (product VARIANT)
 *  - simple-variants:seed sinh 1 variant default invisible (product SIMPLE)
 *    → product.has_variants vẫn = 0, trang detail KHÔNG render picker.
 *
 * Cách dùng:
 *   php artisan simple-variants:seed                # tất cả product chưa có variant
 *   php artisan simple-variants:seed --chunk=2000
 *   php artisan simple-variants:seed --policy=untracked   # cho hàng digital
 */
class SeedSimpleVariantsCommand extends Command
{
    protected $signature = 'simple-variants:seed
        {--chunk=2000 : Số product mỗi batch}
        {--policy=deny : inventory_policy mặc định cho stock seed (deny|backorder|untracked)}';

    protected $description = 'Backfill 1 default variant + product_stock cho product SIMPLE (chưa có variant). Dùng sau products:seed.';

    public function handle(): int
    {
        $chunk    = max(500, (int) $this->option('chunk'));
        $policy   = (StockPolicy::fromName($this->option('policy')) ?? StockPolicy::Deny)->value;
        $warehouseId = (int) (getConfigDb('config_warehouse_id') ?: 1);

        // Đếm trước để biết khối lượng + cho progress bar.
        $remaining = (int) DB::table('product as p')
            ->leftJoin('product_variant as pv', 'pv.product_id', '=', 'p.id')
            ->whereNull('pv.id')
            ->whereNull('p.deleted_at')
            ->count();

        if ($remaining === 0) {
            $this->info('Không còn product nào thiếu variant — bỏ qua.');
            return self::SUCCESS;
        }

        $this->info("Backfill {$remaining} simple product (policy={$this->option('policy')})…");

        DB::disableQueryLog();
        DB::statement('SET unique_checks=0');
        DB::statement('SET foreign_key_checks=0');
        $started = microtime(true);

        $nextVariantId  = ((int) DB::table('product_variant')->max('id')) + 1;
        $nextStockId    = ((int) DB::table('product_stock')->max('id')) + 1;
        $nextMovementId = ((int) DB::table('stock_movement')->max('id')) + 1;

        // Một số cột legacy (`quantity`, `subtract`) đã được đánh dấu sẽ drop
        // ở follow-up sau migration 2026_06_11_000003_unify_simple_product_stock.
        // Detect runtime để command không vỡ khi user đã drop. Default:
        //   - quantity missing  → random on_hand 1..100
        //   - subtract missing  → dùng policy từ --policy flag (deny mặc định)
        $hasQuantity = Schema::hasColumn('product', 'quantity');
        $hasSubtract = Schema::hasColumn('product', 'subtract');

        $selectCols = ['p.id', 'p.weight', 'p.image'];
        if ($hasQuantity) {
            $selectCols[] = 'p.quantity';
        }
        if ($hasSubtract) {
            $selectCols[] = 'p.subtract';
        }
        if (! $hasQuantity || ! $hasSubtract) {
            $this->comment('  (info) Cột legacy đã drop: '
                . (! $hasQuantity ? 'quantity ' : '')
                . (! $hasSubtract ? 'subtract ' : '')
                . '→ dùng default cho stock.');
        }

        $bar = $this->output->createProgressBar($remaining);
        $bar->start();

        $created = 0;

        try {
            DB::table('product as p')
                ->leftJoin('product_variant as pv', 'pv.product_id', '=', 'p.id')
                ->whereNull('pv.id')
                ->whereNull('p.deleted_at')
                ->select($selectCols)
                ->orderBy('p.id')
                ->chunkById($chunk, function ($rows) use (
                    $warehouseId,
                    $policy,
                    $hasQuantity,
                    $hasSubtract,
                    &$nextVariantId,
                    &$nextStockId,
                    &$nextMovementId,
                    &$created,
                    $bar,
                ) {
                    $now = Carbon::now();
                    $variants = $stocks = $movements = [];

                    foreach ($rows as $row) {
                        $vid = $nextVariantId++;
                        $sid = $nextStockId++;
                        $mid = $nextMovementId++;
                        // quantity legacy có thể đã drop → fallback random.
                        $onHand = $hasQuantity
                            ? (int) ($row->quantity ?? 0)
                            : rand(1, 100);
                        // subtract legacy có thể đã drop → dùng --policy flag.
                        $rowPolicy = $hasSubtract
                            ? (((int) ($row->subtract ?? 1)) === 1 ? StockPolicy::Deny->value : StockPolicy::Untracked->value)
                            : $policy;

                        // Giá khởi điểm: product cũ không còn cột price (đã drop) →
                        // sinh giá random cho test, sau đó backfill aggregate bằng
                        // GROUP BY (xem cuối hàm).
                        $price = $this->seedPrice();
                        // regular_price = price (chưa sale). variant_special sẽ
                        // tạo discount sau nếu chạy specials:seed.
                        $regularPrice = $price;

                        $variants[] = [
                            'id'                  => $vid,
                            'product_id'          => $row->id,
                            'sku'                 => null,
                            'attribute_signature' => md5('__default__'),
                            'price'               => $price,
                            'regular_price'       => $regularPrice,
                            'points'              => 0,
                            'weight'              => $row->weight === null ? null : (float) $row->weight,
                            'image'               => $row->image,
                            'is_default'          => 1,
                            'sort_order'          => 0,
                            'created_at'          => $now,
                            'updated_at'          => $now,
                            'deleted_at'          => null,
                        ];

                        $stocks[] = [
                            'id'                 => $sid,
                            'product_variant_id' => $vid,
                            'warehouse_id'       => $warehouseId,
                            'on_hand'            => $onHand,
                            'reserved'           => 0,
                            'subtract'           => $rowPolicy === StockPolicy::Untracked->value ? 0 : 1,
                            'inventory_policy'   => $rowPolicy,
                            'version'            => 0,
                            'created_at'         => $now,
                            'updated_at'         => $now,
                        ];

                        $movements[] = [
                            'id'                 => $mid,
                            'product_variant_id' => $vid,
                            'warehouse_id'       => $warehouseId,
                            'type'               => StockMovementType::Receive->value,
                            'quantity_change'    => $onHand,
                            'on_hand_after'      => $onHand,
                            'reference_type'     => 'seed',
                            'reference_id'       => null,
                            'user_id'            => null,
                            'note'               => 'simple-variants:seed default variant',
                            'created_at'         => $now,
                        ];

                        $created++;
                    }

                    DB::table('product_variant')->insert($variants);
                    DB::table('product_stock')->insert($stocks);
                    DB::table('stock_movement')->insert($movements);

                    $bar->advance(count($rows));
                }, 'p.id', 'id');

            $bar->finish();
            $this->newLine();

            // Backfill aggregate min/max_variant_price trên các product vừa
            // được tạo default variant. has_variants giữ nguyên = 0 (theo
            // ý đồ migration: simple stay simple ở UI).
            $this->info('Backfill min/max_variant_price cho simple product…');
            DB::statement('
                UPDATE product p
                INNER JOIN (
                    SELECT product_id, MIN(price) AS mn, MAX(price) AS mx
                    FROM product_variant
                    WHERE deleted_at IS NULL
                    GROUP BY product_id
                ) v ON v.product_id = p.id
                SET p.min_variant_price = v.mn,
                    p.max_variant_price = v.mx
                WHERE p.min_variant_price IS NULL OR p.max_variant_price IS NULL
            ');

            DB::statement("ALTER TABLE product_variant  AUTO_INCREMENT = {$nextVariantId}");
            DB::statement("ALTER TABLE product_stock    AUTO_INCREMENT = {$nextStockId}");
            DB::statement("ALTER TABLE stock_movement   AUTO_INCREMENT = {$nextMovementId}");
        } finally {
            DB::statement('SET unique_checks=1');
            DB::statement('SET foreign_key_checks=1');
        }

        $elapsed = round(microtime(true) - $started, 2);
        $this->info("Xong: {$created} default variant trong {$elapsed}s (~"
            . round($created / max($elapsed, 0.01)) . ' row/s)');

        return self::SUCCESS;
    }

    /**
     * Sinh giá theo phân bố tương tự SeedProductsCommand::randomPrice() — giữ
     * consistency giữa variant của simple và variant Color×Size.
     */
    private function seedPrice(): int
    {
        $r = rand(1, 100);
        if ($r <= 60) {
            return rand(100_000, 1_000_000);
        }
        if ($r <= 85) {
            return rand(1_000_000, 3_000_000);
        }
        if ($r <= 95) {
            return rand(50_000, 100_000);
        }
        return rand(3_000_000, 10_000_000);
    }
}
