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
        {--truncate : Truncate product + taxonomy (category, manufacturer) trước khi seed}
        {--no-special : Không sinh product_special}
        {--no-filter : Không sinh product_filter}
        {--no-taxonomy : KHÔNG auto-seed category/manufacturer kể cả khi pool rỗng}';

    protected $description = 'Seed N product giả lập (bulk insert) + taxonomy (10 cat cha + 40 cat con + 50 manufacturer) để test filter/sort/paging';

    /**
     * 10 category cha + 40 cat con (4 con / 1 cha). Vietnamese taxonomy phổ
     * biến cho test cluster filter category + breadcrumb 2 cấp.
     */
    private const CATEGORY_TREE = [
        'Thời trang nam'         => ['Áo thun nam', 'Áo sơ mi nam', 'Quần jean nam', 'Giày thể thao nam'],
        'Thời trang nữ'          => ['Đầm váy', 'Áo blouse', 'Quần legging', 'Túi xách nữ'],
        'Mẹ & bé'                => ['Sữa công thức', 'Bỉm tã', 'Đồ chơi trẻ em', 'Ghế ăn dặm'],
        'Điện thoại & phụ kiện'  => ['iPhone', 'Samsung Galaxy', 'Xiaomi', 'Tai nghe Bluetooth'],
        'Laptop & máy tính'      => ['MacBook', 'Dell', 'Asus', 'Chuột & bàn phím'],
        'Đồng hồ & trang sức'    => ['Đồng hồ nam', 'Đồng hồ nữ', 'Vòng tay', 'Nhẫn'],
        'Mỹ phẩm & làm đẹp'      => ['Son môi', 'Kem dưỡng da', 'Mặt nạ', 'Nước hoa'],
        'Nhà cửa & đời sống'     => ['Nồi cơm điện', 'Máy lọc nước', 'Đèn LED', 'Ga gối nệm'],
        'Sách & văn phòng phẩm'  => ['Tiểu thuyết', 'Sách kinh doanh', 'Bút viết', 'Sổ tay'],
        'Thực phẩm & đồ uống'    => ['Cà phê', 'Trà', 'Mỳ ăn liền', 'Đồ ăn vặt'],
    ];

    /**
     * 50 manufacturer. Mix brand quốc tế phổ biến + brand Việt để test
     * filter/group theo nhãn hiệu.
     */
    private const MANUFACTURERS = [
        'Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Huawei', 'Nokia', 'Sony', 'LG',
        'Dell', 'HP', 'Asus', 'Acer', 'Lenovo', 'MSI', 'Razer', 'Logitech', 'Microsoft', 'Intel',
        'Nike', 'Adidas', 'Puma', 'Converse', 'Vans', 'New Balance', 'Reebok', 'Fila', 'Under Armour', 'Skechers',
        "L'Oréal", 'Maybelline', 'Innisfree', 'The Face Shop', 'Estée Lauder', 'MAC', 'Shiseido', 'SK-II', 'Olay', 'Nivea',
        'Vinamilk', 'TH True Milk', 'Trung Nguyên', 'Highlands Coffee', 'Acecook', 'Masan', 'Bibica', 'Kinh Đô', 'Cocoxim', 'Vifon',
    ];

    /**
     * Pool 100 đường dẫn ảnh placeholder. User tự copy ảnh thật vào
     * `public/seed/products/image-{1..100}.jpg` để frontend hiển thị, hoặc
     * dùng service placeholder (vd `https://picsum.photos/...`) qua reverse
     * proxy. Trang detail không crash nếu file thiếu — chỉ broken icon.
     *
     * Mỗi product seed sẽ:
     *  - product.image: random 1 ảnh từ pool (legacy single field)
     *  - product_image: insert 2-3 row gallery (KHÔNG gắn variant — gallery chung product)
     */
    private const IMAGE_PRODUCTS = [
        'seed/products/image-1.jpg',   'seed/products/image-2.jpg',   'seed/products/image-3.jpg',   'seed/products/image-4.jpg',   'seed/products/image-5.jpg',
        'seed/products/image-6.jpg',   'seed/products/image-7.jpg',   'seed/products/image-8.jpg',   'seed/products/image-9.jpg',   'seed/products/image-10.jpg',
        'seed/products/image-11.jpg',  'seed/products/image-12.jpg',  'seed/products/image-13.jpg',  'seed/products/image-14.jpg',  'seed/products/image-15.jpg',
        'seed/products/image-16.jpg',  'seed/products/image-17.jpg',  'seed/products/image-18.jpg',  'seed/products/image-19.jpg',  'seed/products/image-20.jpg',
        'seed/products/image-21.jpg',  'seed/products/image-22.jpg',  'seed/products/image-23.jpg',  'seed/products/image-24.jpg',  'seed/products/image-25.jpg',
        'seed/products/image-26.jpg',  'seed/products/image-27.jpg',  'seed/products/image-28.jpg',  'seed/products/image-29.jpg',  'seed/products/image-30.jpg',
        'seed/products/image-31.jpg',  'seed/products/image-32.jpg',  'seed/products/image-33.jpg',  'seed/products/image-34.jpg',  'seed/products/image-35.jpg',
        'seed/products/image-36.jpg',  'seed/products/image-37.jpg',  'seed/products/image-38.jpg',  'seed/products/image-39.jpg',  'seed/products/image-40.jpg',
        'seed/products/image-41.jpg',  'seed/products/image-42.jpg',  'seed/products/image-43.jpg',  'seed/products/image-44.jpg',  'seed/products/image-45.jpg',
        'seed/products/image-46.jpg',  'seed/products/image-47.jpg',  'seed/products/image-48.jpg',  'seed/products/image-49.jpg',  'seed/products/image-50.jpg',
        'seed/products/image-51.jpg',  'seed/products/image-52.jpg',  'seed/products/image-53.jpg',  'seed/products/image-54.jpg',  'seed/products/image-55.jpg',
        'seed/products/image-56.jpg',  'seed/products/image-57.jpg',  'seed/products/image-58.jpg',  'seed/products/image-59.jpg',  'seed/products/image-60.jpg',
        'seed/products/image-61.jpg',  'seed/products/image-62.jpg',  'seed/products/image-63.jpg',  'seed/products/image-64.jpg',  'seed/products/image-65.jpg',
        'seed/products/image-66.jpg',  'seed/products/image-67.jpg',  'seed/products/image-68.jpg',  'seed/products/image-69.jpg',  'seed/products/image-70.jpg',
        'seed/products/image-71.jpg',  'seed/products/image-72.jpg',  'seed/products/image-73.jpg',  'seed/products/image-74.jpg',  'seed/products/image-75.jpg',
        'seed/products/image-76.jpg',  'seed/products/image-77.jpg',  'seed/products/image-78.jpg',  'seed/products/image-79.jpg',  'seed/products/image-80.jpg',
        'seed/products/image-81.jpg',  'seed/products/image-82.jpg',  'seed/products/image-83.jpg',  'seed/products/image-84.jpg',  'seed/products/image-85.jpg',
        'seed/products/image-86.jpg',  'seed/products/image-87.jpg',  'seed/products/image-88.jpg',  'seed/products/image-89.jpg',  'seed/products/image-90.jpg',
        'seed/products/image-91.jpg',  'seed/products/image-92.jpg',  'seed/products/image-93.jpg',  'seed/products/image-94.jpg',  'seed/products/image-95.jpg',
        'seed/products/image-96.jpg',  'seed/products/image-97.jpg',  'seed/products/image-98.jpg',  'seed/products/image-99.jpg',  'seed/products/image-100.jpg',
    ];

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
                // Truncate product cluster + taxonomy. Thứ tự không quan trọng
                // vì foreign_key_checks đã off.
                foreach ([
                    'product_image', 'product_filter', 'product_special', 'product_category', 'product_description', 'product',
                    'category_description', 'category', 'manufacturer',
                ] as $tbl) {
                    if (Schema::hasTable($tbl)) {
                        DB::table($tbl)->truncate();
                        $this->line("  truncated {$tbl}");
                    }
                }
            }

            if (! $this->option('no-taxonomy')) {
                $this->ensureTaxonomy();
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
            // product_image cũng dùng explicit-id strategy. Track cross-batch
            // để mỗi row có id duy nhất.
            $nextImageId = ((int) DB::table('product_image')->max('id')) + 1;
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            for ($offset = 0; $offset < $count; $offset += $chunk) {
                $batchSize = min($chunk, $count - $offset);
                $batchStartId = $startId + $offset;

                [$products, $descriptions, $categories, $specials, $filters, $images]
                    = $this->buildBatch($batchStartId, $batchSize, $pools, $nextImageId);

                DB::table('product')->insert($products);
                if ($descriptions) {
                    DB::table('product_description')->insert($descriptions);
                }
                if ($categories) {
                    DB::table('product_category')->insert($categories);
                }
                if ($specials && !$this->option('no-special')) {
                    DB::table('product_special')->insert($specials);
                }
                if ($filters  && !$this->option('no-filter')) {
                    DB::table('product_filter')->insert($filters);
                }
                if ($images) {
                    // MySQL prepared statement limit = 65535 placeholders.
                    // product_image có 16 cột → mỗi insert tối đa ~4000 row
                    // (chừa biên độ an toàn). chunk=2000 product × max 3 ảnh
                    // = 6000 row → split thành 2 sub-batch.
                    foreach (array_chunk($images, 3000) as $imageChunk) {
                        DB::table('product_image')->insert($imageChunk);
                    }
                    $nextImageId += count($images);
                }

                $bar->advance($batchSize);
            }

            $bar->finish();
            $this->newLine();

            // Reset AUTO_INCREMENT cho an toàn (bulk insert explicit id có thể không cập nhật)
            $newMax = $startId + $count;
            DB::statement("ALTER TABLE product AUTO_INCREMENT = {$newMax}");
            DB::statement("ALTER TABLE product_image AUTO_INCREMENT = {$nextImageId}");
        } finally {
            DB::statement('SET unique_checks=1');
            DB::statement('SET foreign_key_checks=1');
        }

        $elapsed = round(microtime(true) - $started, 2);
        $this->info("Xong: {$count} product trong {$elapsed}s (~" . round($count / max($elapsed, 0.01)) . " row/s)");
        $this->info('Nhớ flush cache: php artisan cache:clear');

        return self::SUCCESS;
    }

    /**
     * Auto-seed category + manufacturer khi pool rỗng. Idempotent: chỉ seed
     * nếu count == 0, không append vào pool có sẵn (tránh nhân đôi sau
     * mỗi lần chạy command).
     *
     * Khi user đã `--truncate` thì 2 bảng đều rỗng → seed full. Khi chưa
     * truncate mà admin có dữ liệu thật, count > 0 → skip, dùng dữ liệu admin.
     */
    private function ensureTaxonomy(): void
    {
        $categoryCount = (int) DB::table('category')->count();
        if ($categoryCount === 0) {
            $this->seedCategories();
        } else {
            $this->line("  category đã có {$categoryCount} row — skip seed.");
        }

        $manufacturerCount = (int) DB::table('manufacturer')->count();
        if ($manufacturerCount === 0) {
            $this->seedManufacturers();
        } else {
            $this->line("  manufacturer đã có {$manufacturerCount} row — skip seed.");
        }
    }

    /**
     * Seed 10 cat cha (parent_id=0) + 40 cat con (parent_id = id cha tương
     * ứng). Insert 2 bước vì cần biết id cha trước khi insert con — bulk
     * insert không trả id mảng nên dùng explicit-id strategy:
     *
     *   $startId = max(id) + 1 (hoặc 1 nếu trống)
     *   parent_i.id = startId + i             (i = 0..9)
     *   child_j.id = startId + 10 + j         (j = 0..39)
     *   child_j.parent_id = parent_(j/4).id
     */
    private function seedCategories(): void
    {
        $now = Carbon::now();
        $startId = ((int) DB::table('category')->max('id')) + 1;

        $categories = $descriptions = [];

        $parentTitles = array_keys(self::CATEGORY_TREE);
        $parentIdByTitle = [];

        // 10 cat cha
        foreach ($parentTitles as $i => $title) {
            $id = $startId + $i;
            $parentIdByTitle[$title] = $id;
            $categories[] = $this->categoryRow($id, parentId: 0, sortOrder: $i, now: $now);
            $descriptions[] = $this->categoryDescriptionRow($id, $title);
        }

        // 40 cat con
        $childIdOffset = $startId + count($parentTitles);
        $childIndex = 0;
        foreach (self::CATEGORY_TREE as $parentTitle => $children) {
            $parentId = $parentIdByTitle[$parentTitle];
            foreach ($children as $childSort => $childTitle) {
                $id = $childIdOffset + $childIndex;
                $categories[] = $this->categoryRow($id, parentId: $parentId, sortOrder: $childSort, now: $now);
                $descriptions[] = $this->categoryDescriptionRow($id, $childTitle);
                $childIndex++;
            }
        }

        DB::table('category')->insert($categories);
        DB::table('category_description')->insert($descriptions);

        // Reset AUTO_INCREMENT để insert real sau này không collide.
        $nextId = $childIdOffset + $childIndex;
        DB::statement("ALTER TABLE category AUTO_INCREMENT = {$nextId}");

        $this->line('  seeded ' . count($categories) . ' category (' . count($parentTitles) . ' cha + ' . $childIndex . ' con)');
    }

    private function categoryRow(int $id, int $parentId, int $sortOrder, Carbon $now): array
    {
        return [
            'id'         => $id,
            'parent_id'  => $parentId,
            'image'      => null,
            'icon'       => null,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }

    private function categoryDescriptionRow(int $categoryId, string $title): array
    {
        return [
            'category_id'      => $categoryId,
            'language_code'    => 'vi',
            'title'            => $title,
            'description'      => 'Danh mục ' . $title,
            'slug'             => Str::slug($title),
            'content'          => '<p>Nội dung cho danh mục ' . e($title) . '.</p>',
            'meta_title'       => $title,
            'meta_description' => 'Meta ' . $title,
        ];
    }

    /**
     * Seed 50 manufacturer single-row (không có description i18n riêng — name
     * + meta nằm thẳng trên bảng manufacturer).
     */
    private function seedManufacturers(): void
    {
        $now = Carbon::now();
        $startId = ((int) DB::table('manufacturer')->max('id')) + 1;

        $rows = [];
        foreach (self::MANUFACTURERS as $i => $name) {
            $rows[] = [
                'id'               => $startId + $i,
                'name'             => $name,
                'image'            => null,
                'sort_order'       => $i,
                'meta_title'       => $name,
                'meta_description' => 'Sản phẩm chính hãng ' . $name,
                'created_at'       => $now,
                'updated_at'       => $now,
                'deleted_at'       => null,
            ];
        }

        DB::table('manufacturer')->insert($rows);

        $nextId = $startId + count($rows);
        DB::statement("ALTER TABLE manufacturer AUTO_INCREMENT = {$nextId}");

        $this->line('  seeded ' . count($rows) . ' manufacturer');
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

    private function buildBatch(int $startId, int $size, array $pools, int $imageStartId): array
    {
        $now      = Carbon::now();
        $products = $descriptions = $categories = $specials = $filters = $images = [];
        $imagePool = self::IMAGE_PRODUCTS;
        $poolCount = count($imagePool);
        $imageId = $imageStartId;

        for ($i = 0; $i < $size; $i++) {
            $id    = $startId + $i;
            $price = $this->randomPrice();

            // Main image: pick 1 random từ pool. Gallery: pick 2-3 distinct
            // shuffle slice (cho phép trùng với main — realistic: ảnh đại diện
            // thường cũng xuất hiện trong gallery).
            $mainImage = $imagePool[array_rand($imagePool)];
            $galleryCount = rand(2, min(3, $poolCount));
            $shuffled = $imagePool;
            shuffle($shuffled);
            $galleryImages = array_slice($shuffled, 0, $galleryCount);

            $products[] = [
                'id'                => $id,
                'model'             => 'PRD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'sku'               => 'SKU-' . $id . '-' . Str::upper(Str::random(4)),
                'quantity'          => rand(0, 100) > 15 ? rand(1, 500) : 0, // ~15% hết hàng
                'badge'             => $this->randomBadge(),
                'image'             => $mainImage,
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
                    'date_end'      => rand(0, 1) ? $now->copy()->addDays(rand(1, 60)) : null,
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

            // Gallery: 2-3 row product_image (KHÔNG gắn variant — gallery chung product).
            // Schema sau refactor: type='gallery', is_active=1, sort_order theo
            // thứ tự shuffle. alt NULL → blade fallback product.name.
            foreach ($galleryImages as $sort => $imgPath) {
                $images[] = [
                    'id'                 => $imageId++,
                    'product_id'         => $id,
                    'product_variant_id' => null,
                    'image'              => $imgPath,
                    'alt'                => null,
                    'title'              => null,
                    'type'               => 'gallery',
                    'width'              => null,
                    'height'             => null,
                    'file_size'          => null,
                    'mime'               => null,
                    'sort_order'         => $sort,
                    'is_active'          => 1,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                    'deleted_at'         => null,
                ];
            }
        }

        return [$products, $descriptions, $categories, $specials, $filters, $images];
    }

    /**
     * Phân bố giá realistic: phần lớn 100k–1tr, một số ngoài range.
     */
    private function randomPrice(): int
    {
        $r = rand(1, 100);
        if ($r <= 60) {
            return rand(100_000, 1_000_000);
        }    // 60%
        if ($r <= 85) {
            return rand(1_000_000, 3_000_000);
        }  // 25%
        if ($r <= 95) {
            return rand(50_000, 100_000);
        }       // 10%
        return rand(3_000_000, 10_000_000);               // 5%
    }

    private function randomBadge(): ?string
    {
        $pool = [null, null, null, null, 'feature', 'new', 'hot']; // ~57% null
        return $pool[array_rand($pool)];
    }
}
