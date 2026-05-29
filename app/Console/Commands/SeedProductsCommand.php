<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed dữ liệu product giả lập cho test hiệu năng (filter price, sort, paging).
 *
 * Dùng raw DB::table()->insert() theo chunk thay vì Eloquent ::create() để:
 *  - Không trigger model events / observer / auditing (OwenIt\Auditing).
 *  - Tận dụng bulk insert thay vì 1 INSERT/row.
 *  - Bypass Eloquent overhead (~50× nhanh hơn cho dataset lớn).
 *
 * Cách dùng:
 *   php artisan products:seed                         # tạo 50000 product
 *   php artisan products:seed 10000                   # tạo 10000
 *   php artisan products:seed 50000 --chunk=2000      # chunk size
 *   php artisan products:seed 50000 --truncate        # xóa data cũ trước
 *   php artisan products:seed 50000 --no-special      # không sinh product_special
 *   php artisan products:seed 50000 --no-filter       # không sinh product_filter
 */
class SeedProductsCommand extends Command
{
    protected $signature = 'products:seed
        {count=50000 : Số product cần tạo}
        {--chunk=2000 : Số row mỗi batch insert}
        {--truncate : Truncate bảng product/product_description/product_category/product_special/product_filter trước khi seed}
        {--no-special : Không sinh product_special}
        {--no-filter : Không sinh product_filter}';

    protected $description = 'Seed N product giả lập (bulk insert) để test filter/sort/paging';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $chunk = max(100, (int) $this->option('chunk'));

        if ($this->option('truncate') && !$this->confirm("Xóa toàn bộ product & các bảng phụ trước khi seed?", true)) {
            return self::FAILURE;
        }

        $this->info("Bắt đầu seed {$count} product (chunk={$chunk})…");
        $started = microtime(true);

        DB::disableQueryLog();
        DB::statement('SET unique_checks=0');
        DB::statement('SET foreign_key_checks=0');

        try {
            if ($this->option('truncate')) {
                foreach (['product_filter', 'product_special', 'product_category', 'product_description', 'product'] as $tbl) {
                    if (Schema::hasTable($tbl)) {
                        DB::table($tbl)->truncate();
                        $this->line("  truncated {$tbl}");
                    }
                }
            }

            $pools = $this->loadPools();
            if (empty($pools['manufacturers'])) {
                $this->warn('Bảng manufacturer rỗng — manufacturer_id sẽ NULL hết.');
            }
            if (empty($pools['categories'])) {
                $this->warn('Bảng category rỗng — sẽ không sinh product_category.');
            }
            if (empty($pools['filter_values_by_filter']) && !$this->option('no-filter')) {
                $this->warn('Bảng filter_value rỗng — bỏ qua product_filter.');
            }

            $startId = ((int) DB::table('product')->max('id')) + 1;
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            for ($offset = 0; $offset < $count; $offset += $chunk) {
                $batchSize = min($chunk, $count - $offset);
                $batchStartId = $startId + $offset;

                [$products, $descriptions, $categories, $specials, $filters]
                    = $this->buildBatch($batchStartId, $batchSize, $pools);

                DB::table('product')->insert($products);
                if ($descriptions) DB::table('product_description')->insert($descriptions);
                if ($categories)   DB::table('product_category')->insert($categories);
                if ($specials && !$this->option('no-special'))   DB::table('product_special')->insert($specials);
                if ($filters  && !$this->option('no-filter'))    DB::table('product_filter')->insert($filters);

                $bar->advance($batchSize);
            }

            $bar->finish();
            $this->newLine();

            // Reset AUTO_INCREMENT cho an toàn (bulk insert explicit id có thể không cập nhật)
            $newMax = $startId + $count;
            DB::statement("ALTER TABLE product AUTO_INCREMENT = {$newMax}");
        } finally {
            DB::statement('SET unique_checks=1');
            DB::statement('SET foreign_key_checks=1');
        }

        $elapsed = round(microtime(true) - $started, 2);
        $this->info("Xong: {$count} product trong {$elapsed}s (~" . round($count / max($elapsed, 0.01)) . " row/s)");
        $this->info('Nhớ flush cache: php artisan cache:clear');

        return self::SUCCESS;
    }

    private function loadPools(): array
    {
        // Group filter_value theo filter_id để insert product_filter không vi phạm
        // composite PK (product_id, filter_id) — 1 product chỉ 1 value cho mỗi filter.
        $filterValuesByFilter = DB::table('filter_value')
            ->select('id', 'filter_id')
            ->get()
            ->groupBy('filter_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        return [
            'manufacturers'         => DB::table('manufacturer')->pluck('id')->all(),
            'categories'            => DB::table('category')->pluck('id')->all(),
            'filter_values_by_filter' => $filterValuesByFilter,
            'stock_statuses'        => Schema::hasTable('stock_status')
                ? DB::table('stock_status')->pluck('id')->all()
                : [],
        ];
    }

    private function buildBatch(int $startId, int $size, array $pools): array
    {
        $now      = Carbon::now();
        $products = $descriptions = $categories = $specials = $filters = [];

        for ($i = 0; $i < $size; $i++) {
            $id    = $startId + $i;
            $price = $this->randomPrice();

            $products[] = [
                'id'                => $id,
                'model'             => 'PRD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'sku'               => 'SKU-' . $id . '-' . Str::upper(Str::random(4)),
                'quantity'          => rand(0, 100) > 15 ? rand(1, 500) : 0, // ~15% hết hàng
                'badge'             => $this->randomBadge(),
                'image'             => 'no_img.png',
                'video'             => null,
                'shipping'          => 1,
                'link_sale'         => null,
                'price'             => $price,
                'points'            => 0,
                'date_available'    => $now->copy()->subDays(rand(0, 365))->toDateString(),
                'weight'            => rand(50, 5000),
                'length'            => rand(5, 100),
                'width'             => rand(5, 100),
                'height'            => rand(5, 100),
                'subtract'          => 1,
                'minimum'           => 1,
                'rating'            => rand(0, 50) / 10,
                'total_rating'      => rand(0, 200),
                'viewed'            => rand(0, 5000),
                'link_sale_custom'  => null,
                'is_add_cart'       => 1,
                'is_custom'         => 0,
                'is_review'         => 1,
                'manufacturer_id'   => $pools['manufacturers'] ? $pools['manufacturers'][array_rand($pools['manufacturers'])] : null,
                'stock_status_id'   => $pools['stock_statuses'] ? $pools['stock_statuses'][array_rand($pools['stock_statuses'])] : null,
                'weight_class_id'   => null,
                'created_at'        => $now->copy()->subDays(rand(0, 720)),
                'updated_at'        => $now,
                'deleted_at'        => null,
            ];

            $name = 'Sản phẩm seed #' . $id;
            $descriptions[] = [
                'product_id'       => $id,
                'language_code'    => 'vi',
                'name'             => $name,
                'description'      => 'Mô tả ngắn cho ' . $name . '. Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                'slug'             => Str::slug($name) . '-' . $id,
                'content'          => '<p>Nội dung chi tiết cho ' . $name . '.</p>',
                'tag'              => 'tag-' . ($id % 50),
                'meta_title'       => $name,
                'meta_description' => 'Meta ' . $name,
            ];

            // 1–3 category random
            if ($pools['categories']) {
                $catCount = rand(1, min(3, count($pools['categories'])));
                $picked   = (array) array_rand(array_flip($pools['categories']), $catCount);
                foreach ($picked as $catId) {
                    $categories[] = [
                        'product_id'  => $id,
                        'category_id' => $catId,
                    ];
                }
            }

            // ~30% có 1 product_special
            if (rand(1, 100) <= 30) {
                $promo = (int) round($price * (rand(50, 90) / 100)); // sale 10–50%
                $specials[] = [
                    'product_id'    => $id,
                    'user_group_id' => 1, // mặc định guest group
                    'priority'      => rand(1, 10),
                    'price'         => $promo,
                    'date_start'    => rand(0, 1) ? $now->copy()->subDays(rand(0, 30)) : null,
                    'date_end'      => rand(0, 1) ? $now->copy()->addDays(rand(1, 60))  : null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            // 0–3 filter random; mỗi filter pick 1 filter_value (tránh đụng composite PK)
            if (!empty($pools['filter_values_by_filter'])) {
                $allFilterIds = array_keys($pools['filter_values_by_filter']);
                $fCount = rand(0, min(3, count($allFilterIds)));
                if ($fCount > 0) {
                    $pickedFilterIds = (array) array_rand(array_flip($allFilterIds), $fCount);
                    foreach ($pickedFilterIds as $fId) {
                        $valuesOfFilter = $pools['filter_values_by_filter'][$fId];
                        $filters[] = [
                            'product_id'      => $id,
                            'filter_id'       => $fId,
                            'filter_value_id' => $valuesOfFilter[array_rand($valuesOfFilter)],
                        ];
                    }
                }
            }
        }

        return [$products, $descriptions, $categories, $specials, $filters];
    }

    /**
     * Phân bố giá realistic: phần lớn 100k–1tr, một số ngoài range.
     */
    private function randomPrice(): int
    {
        $r = rand(1, 100);
        if ($r <= 60) return rand(100_000, 1_000_000);    // 60%
        if ($r <= 85) return rand(1_000_000, 3_000_000);  // 25%
        if ($r <= 95) return rand(50_000, 100_000);       // 10%
        return rand(3_000_000, 10_000_000);               // 5%
    }

    private function randomBadge(): ?string
    {
        $pool = [null, null, null, null, 'feature', 'new', 'hot']; // ~57% null
        return $pool[array_rand($pool)];
    }
}
