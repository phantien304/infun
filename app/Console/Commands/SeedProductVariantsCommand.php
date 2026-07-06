<?php

namespace App\Console\Commands;

use App\Models\Entities\Option;
use App\Models\Entities\ProductVariant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed product_variant + cluster (attribute, stock, movement) cho test schema
 * mới (xem CLAUDE.md mục "Schema cluster variant").
 *
 * Mỗi product được chọn (theo %) sẽ nhận 2–3 variant random từ grid Color × Size:
 *  - Color: 6 màu (Blue/Red/Yellow/Green/Black/White), type='image', role=variant.
 *    Mỗi màu ánh xạ 1 ảnh cố định → test logic precompute variant_image trong
 *    ProductOptionService (mọi variant cùng màu cùng image → consistent → service
 *    giữ trong $variantImageByValueId; size không driving image → loại đúng).
 *  - Size: 5 size (S/M/L/XL/XXL), type='radio', role=variant.
 *
 * Cluster tạo cho mỗi variant:
 *  - 1 row product_variant (price = base tự sinh × random 0.85–1.15)
 *  - 2 rows product_variant_attribute (1 cho Color, 1 cho Size)
 *  - 1 row product_stock (warehouse_id=1, on_hand 1–200)
 *  - 1 row stock_movement type='receive' để khởi tạo audit log
 *  - 2 rows product_option declaration (Color + Size) cho mỗi product được chọn
 *    (KHÔNG duplicate giữa các variant của cùng product).
 *
 * Dùng explicit-id strategy + bulk insert + tắt FK/unique check tương tự
 * SeedProductsCommand. Bypass Eloquent events / observer.
 *
 * Sau khi seed: backfill product.has_variants + min/max_variant_price theo
 * GROUP BY product_id để filter/sort list page hoạt động.
 *
 * Cách dùng:
 *   php artisan products:seed 50000 --truncate    # seed product trước
 *   php artisan variants:seed --truncate          # rồi gắn variant
 *   php artisan variants:seed --percent=60        # chỉ 60% product có variant
 *   php artisan variants:seed --min=2 --max=4     # 2–4 variant/product
 */
class SeedProductVariantsCommand extends Command
{
    protected $signature = 'variants:seed
        {--chunk=100 : Số product mỗi batch xử lý}
        {--percent=100 : % product được gắn variant (0–100)}
        {--min=2 : Số variant tối thiểu mỗi product (chỉ áp dụng khi KHÔNG --full)}
        {--max=3 : Số variant tối đa mỗi product (chỉ áp dụng khi KHÔNG --full)}
        {--full : Sinh ĐẦY ĐỦ Cartesian (mọi tổ hợp Color × Size). Khuyến nghị khi test UX variant availability — tránh false "hết hàng" do combo không tồn tại}
        {--with-custom-fields=0 : % product nhận 1-2 option custom field (text/email/phone/textarea/radio/select). 0 = skip}
        {--special-percent=30 : % product được gắn product_variant_special trên default variant (0 = skip)}
        {--truncate : Xóa cluster variant + custom field declaration trước khi seed}';

    protected $description = 'Seed product_variant + cluster (attribute, stock, movement) + custom field options cho test schema mới';

    /** Marker để phân biệt option seed khỏi option thật của admin. */
    private const SEED_COLOR_NAME = '[SEED] Màu sắc';
    private const SEED_SIZE_NAME  = '[SEED] Size';

    /**
     * Pool 6 custom field options đại diện đủ type ProductOptionService hỗ trợ:
     *  - text/email/phone/textarea: user nhập trực tiếp, KHÔNG cần picker preset
     *  - radio/select: cần picker preset (option_value + product_option_value rows)
     *
     * Mỗi entry có:
     *  - name: description tiếng Việt + marker [SEED] để truncate phân biệt
     *  - type: render type ở blade _option.blade.php
     *  - default: giá trị mặc định lưu vào product_option.value (NULL cho picker)
     *  - required: 1 nếu bắt buộc (vd SĐT giao hàng)
     *  - values: list value name cho picker (radio/select); rỗng nếu input free
     */
    private const CUSTOM_FIELDS = [
        ['name' => '[SEED] Lời chúc in áo',  'type' => 'text',     'default' => 'Chúc mừng sinh nhật', 'required' => 0, 'values' => []],
        ['name' => '[SEED] Email người nhận', 'type' => 'email',    'default' => '',                    'required' => 0, 'values' => []],
        ['name' => '[SEED] SĐT giao hàng',    'type' => 'phone',    'default' => '',                    'required' => 1, 'values' => []],
        ['name' => '[SEED] Ghi chú đơn hàng', 'type' => 'textarea', 'default' => '',                    'required' => 0, 'values' => []],
        ['name' => '[SEED] Chọn dây áo',      'type' => 'radio',    'default' => null,                  'required' => 0, 'values' => ['Dây đen', 'Dây nâu', 'Dây đỏ']],
        ['name' => '[SEED] Chọn loại in',     'type' => 'select',   'default' => null,                  'required' => 0, 'values' => ['In lụa', 'In nhiệt']],
    ];

    /**
     * Mỗi màu ánh xạ 1 ảnh cố định (đường dẫn relative tới public storage hoặc
     * CDN). Quan trọng cho test: mọi variant cùng màu phải cùng image để
     * ProductOptionService::buildVariantOptions detect "color drives image".
     */
    private const COLORS = [
        ['name' => 'Blue',   'image' => 'seed/variants/blue.jpg'],
        ['name' => 'Red',    'image' => 'seed/variants/red.jpg'],
        ['name' => 'Yellow', 'image' => 'seed/variants/yellow.jpg'],
        ['name' => 'Green',  'image' => 'seed/variants/green.jpg'],
        ['name' => 'Black',  'image' => 'seed/variants/black.jpg'],
        ['name' => 'White',  'image' => 'seed/variants/white.jpg'],
    ];

    private const SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

    public function handle(): int
    {
        $chunk   = max(50, (int) $this->option('chunk'));
        $percent = max(0, min(100, (int) $this->option('percent')));
        $min     = max(1, (int) $this->option('min'));
        $max     = max($min, (int) $this->option('max'));

        if ($this->option('truncate')
            && ! $this->confirm('Xóa toàn bộ cluster variant (product_variant, _attribute, _description, product_stock, stock_movement) + declaration product_option của seed options?', true)) {
            return self::FAILURE;
        }

        DB::disableQueryLog();
        DB::statement('SET unique_checks=0');
        DB::statement('SET foreign_key_checks=0');
        $started = microtime(true);

        try {
            [$colorOptionId, $sizeOptionId, $colorValueIds, $sizeValueIds, $imageByColorValueId]
                = $this->ensureSeedOptions();

            if ($this->option('truncate')) {
                $this->truncateClusters($colorOptionId, $sizeOptionId);
            }

            $totalProducts = (int) DB::table('product')->whereNull('deleted_at')->count();
            if ($totalProducts === 0) {
                $this->warn('Bảng product rỗng — chạy `php artisan products:seed` trước.');
                return self::FAILURE;
            }
            $this->info("Tổng {$totalProducts} product. Gắn variant cho ~{$percent}%, mỗi product {$min}–{$max} variant.");

            // Explicit-id strategy: lock các MAX id để chunk tính offset không
            // phải insertGetId từng row. AUTO_INCREMENT sẽ reset sau.
            $nextVariantId  = ((int) DB::table('product_variant')->max('id')) + 1;
            $nextStockId    = ((int) DB::table('product_stock')->max('id')) + 1;
            $nextMovementId = ((int) DB::table('stock_movement')->max('id')) + 1;

            $bar = $this->output->createProgressBar($totalProducts);
            $bar->start();

            $touchedProductIds = [];
            $totalVariantsCreated = 0;

            DB::table('product')
                ->whereNull('deleted_at')
                ->select('id')
                ->orderBy('id')
                ->chunk($chunk, function ($products) use (
                    $percent,
                    $min,
                    $max,
                    $colorOptionId,
                    $sizeOptionId,
                    $colorValueIds,
                    $sizeValueIds,
                    $imageByColorValueId,
                    &$nextVariantId,
                    &$nextStockId,
                    &$nextMovementId,
                    &$touchedProductIds,
                    &$totalVariantsCreated,
                    $bar,
                ) {
                    [$variants, $attributes, $stocks, $movements, $variantSpecials, $touched] =
                        $this->buildBatch(
                            $products,
                            $percent,
                            $min,
                            $max,
                            $colorOptionId,
                            $sizeOptionId,
                            $colorValueIds,
                            $sizeValueIds,
                            $imageByColorValueId,
                            $nextVariantId,
                            $nextStockId,
                            $nextMovementId,
                        );

                    if ($variants) {
                        DB::table('product_variant')->insert($variants);
                    }
                    if ($attributes) {
                        DB::table('product_variant_attribute')->insert($attributes);
                    }
                    if ($stocks) {
                        DB::table('product_stock')->insert($stocks);
                    }
                    if ($movements) {
                        DB::table('stock_movement')->insert($movements);
                    }
                    if ($variantSpecials) {
                        DB::table('product_variant_special')->insert($variantSpecials);
                    }

                    $nextVariantId        += count($variants);
                    $nextStockId          += count($stocks);
                    $nextMovementId       += count($movements);
                    $totalVariantsCreated += count($variants);
                    $touchedProductIds     = array_merge($touchedProductIds, $touched);

                    $bar->advance(count($products));
                });

            $bar->finish();
            $this->newLine();

            $this->info("Backfill product.has_variants + min/max_variant_price …");
            $this->backfillProductAggregates();

            // Reset AUTO_INCREMENT sang max+1 để insert real sau đó không
            // collide với id seed (explicit id không tự cập nhật counter).
            DB::statement("ALTER TABLE product_variant  AUTO_INCREMENT = {$nextVariantId}");
            DB::statement("ALTER TABLE product_stock    AUTO_INCREMENT = {$nextStockId}");
            DB::statement("ALTER TABLE stock_movement   AUTO_INCREMENT = {$nextMovementId}");

            // Custom field flow — chạy SAU variant flow (cùng transaction
            // disable-checks). Mọi product được variants seed vẫn có thể
            // thêm custom field (mixed product realistic).
            $cfPercent = max(0, min(100, (int) $this->option('with-custom-fields')));
            if ($cfPercent > 0) {
                $this->seedCustomFields($cfPercent, $chunk);
            }
        } finally {
            DB::statement('SET unique_checks=1');
            DB::statement('SET foreign_key_checks=1');
        }

        $elapsed = round(microtime(true) - $started, 2);
        $touchedCount = count(array_unique($touchedProductIds));
        $this->info("Xong: {$totalVariantsCreated} variant trên {$touchedCount} product trong {$elapsed}s (~"
            . round($totalVariantsCreated / max($elapsed, 0.01)) . ' variant/s)');
        $this->info('Khuyến nghị: php artisan cache:clear && ANALYZE TABLE product_variant, product_variant_attribute, product_stock;');

        return self::SUCCESS;
    }

    /**
     * Ensure 6 custom field options (role=ROLE_CUSTOM_FIELD) + picker preset
     * values cho radio/select.
     *
     * @return array<int, array{type:string, default:?string, required:int, value_ids:array<int>}>
     *   Keyed by option_id.
     */
    private function ensureCustomFieldOptions(): array
    {
        $result = [];
        foreach (self::CUSTOM_FIELDS as $cf) {
            $optionId = $this->upsertOption($cf['name'], $cf['type'], getCoreConfig('option.role_custom_field'));

            // Picker preset cho radio/select. text/email/phone/textarea không
            // có values.
            $valueIds = [];
            foreach ($cf['values'] as $valueName) {
                $valueIds[] = $this->upsertOptionValue($optionId, $valueName, null);
            }

            $result[$optionId] = [
                'type'      => $cf['type'],
                'default'   => $cf['default'],
                'required'  => $cf['required'],
                'value_ids' => $valueIds,
            ];
        }
        return $result;
    }

    /**
     * Gắn custom field options cho % product. 1 product có thể nhận 1-2 custom
     * field random + cũng có thể đang có variant Color/Size (mixed product).
     *
     * product_option giờ có surrogate PK `id`; khóa tự nhiên (product_id,
     * option_id) là UNIQUE — insertOrIgnore vẫn tự dedupe khi product đã có
     * declaration đó. Picker (product_option_value) cần product_option_id nên
     * được resolve sau khi insert declaration.
     */
    private function seedCustomFields(int $percent, int $chunk): void
    {
        $cfOptions = $this->ensureCustomFieldOptions();
        $cfOptionIds = array_keys($cfOptions);

        // Truncate cleanup: xóa declaration + picker preset của seed custom
        // field options. KHÔNG đụng option/option_value gốc (giữ idempotent).
        if ($this->option('truncate')) {
            $deletedDecl = DB::table('product_option')
                ->whereIn('option_id', $cfOptionIds)
                ->delete();
            $deletedPicker = DB::table('product_option_value')
                ->whereIn('option_id', $cfOptionIds)
                ->delete();
            $this->line("  xóa {$deletedDecl} product_option + {$deletedPicker} product_option_value (custom field seed)");
        }

        $totalProducts = (int) DB::table('product')->whereNull('deleted_at')->count();
        $this->info("Custom field: gắn cho ~{$percent}% trong {$totalProducts} product (1-2 field/product)…");

        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        $totalDecl = 0;
        $totalPicker = 0;

        DB::table('product')
            ->whereNull('deleted_at')
            ->select('id')
            ->orderBy('id')
            ->chunk($chunk, function ($products) use (
                $percent,
                $cfOptions,
                $cfOptionIds,
                &$totalDecl,
                &$totalPicker,
                $bar,
            ) {
                $now = Carbon::now();
                $declarations = $pickerValues = [];

                foreach ($products as $product) {
                    if (rand(1, 100) > $percent) {
                        continue;
                    }

                    // Pick 1-2 custom field option ngẫu nhiên.
                    $shuffled = $cfOptionIds;
                    shuffle($shuffled);
                    $pickCount = rand(1, min(2, count($shuffled)));
                    $picked = array_slice($shuffled, 0, $pickCount);

                    foreach ($picked as $sort => $optionId) {
                        $cf = $cfOptions[$optionId];

                        $declarations[] = [
                            'product_id' => $product->id,
                            'option_id'  => $optionId,
                            'value'      => $cf['default'],
                            'required'   => $cf['required'],
                            // sort_order >= 10 chỉ để xếp thứ tự GIỮA các custom
                            // field. Variant luôn render trước (buildOptions nối
                            // variant groups trước custom field), không phụ thuộc
                            // sort_order này nữa.
                            'sort_order' => 10 + $sort,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        // Picker preset cho radio/select — product_option_value
                        // rows. product_option_id chưa biết ở đây (declaration
                        // chưa insert); resolve sau khi insert product_option.
                        // Giữ product_id/option_id để tra ngược id.
                        foreach ($cf['value_ids'] as $povSort => $valueId) {
                            $pickerValues[] = [
                                'product_id'      => $product->id,
                                'option_id'       => $optionId,
                                'option_value_id' => $valueId,
                                'image'           => null,
                                'sort_order'      => $povSort,
                                'created_at'      => $now,
                                'updated_at'      => $now,
                            ];
                        }
                    }
                }

                if ($declarations) {
                    DB::table('product_option')->insertOrIgnore($declarations);
                    $totalDecl += count($declarations);
                }
                if ($pickerValues) {
                    // product_option_value.product_option_id giờ NOT NULL —
                    // resolve id của declaration vừa insert theo (product_id,
                    // option_id) (khóa tự nhiên UNIQUE trên product_option).
                    $poMap = DB::table('product_option')
                        ->whereIn('product_id', array_values(array_unique(array_column($pickerValues, 'product_id'))))
                        ->whereIn('option_id', $cfOptionIds)
                        ->get(['id', 'product_id', 'option_id'])
                        ->keyBy(fn ($r) => $r->product_id . ':' . $r->option_id);

                    $resolved = [];
                    foreach ($pickerValues as $pv) {
                        $po = $poMap->get($pv['product_id'] . ':' . $pv['option_id']);
                        if (! $po) {
                            continue; // declaration bị dedupe/thiếu — bỏ picker mồ côi
                        }
                        $pv['product_option_id'] = (int) $po->id;
                        $resolved[] = $pv;
                    }

                    if ($resolved) {
                        DB::table('product_option_value')->insertOrIgnore($resolved);
                        $totalPicker += count($resolved);
                    }
                }

                $bar->advance(count($products));
            });

        $bar->finish();
        $this->newLine();
        $this->info("Custom field xong: {$totalDecl} declaration + {$totalPicker} picker value.");
    }

    /**
     * Đảm bảo 2 seed option (Color + Size) + values tồn tại. Marker bằng
     * description.name (vd "[SEED] Màu sắc") để cách ly khỏi option thật.
     *
     * @return array{0:int, 1:int, 2:array<int>, 3:array<int>, 4:array<int,string>}
     *   [color_option_id, size_option_id, color_value_ids, size_value_ids,
     *    image_by_color_value_id]
     */
    private function ensureSeedOptions(): array
    {
        $colorOptionId = $this->upsertOption(self::SEED_COLOR_NAME, 'image', getCoreConfig('option.role_variant'));
        $sizeOptionId  = $this->upsertOption(self::SEED_SIZE_NAME, 'radio', getCoreConfig('option.role_variant'));

        $colorValueIds = [];
        $imageByColorValueId = [];
        foreach (self::COLORS as $color) {
            $id = $this->upsertOptionValue($colorOptionId, $color['name'], $color['image']);
            $colorValueIds[] = $id;
            $imageByColorValueId[$id] = $color['image'];
        }

        $sizeValueIds = [];
        foreach (self::SIZES as $sizeName) {
            $sizeValueIds[] = $this->upsertOptionValue($sizeOptionId, $sizeName, null);
        }

        return [$colorOptionId, $sizeOptionId, $colorValueIds, $sizeValueIds, $imageByColorValueId];
    }

    /**
     * Tìm option theo description.name; nếu chưa có, tạo option + description (vi).
     *
     * @param  int  $role  Option::ROLE_VARIANT | Option::ROLE_CUSTOM_FIELD
     */
    private function upsertOption(string $name, string $type, int $role): int
    {
        $existing = DB::table('option as o')
            ->join('option_description as od', 'od.option_id', '=', 'o.id')
            ->where('od.name', $name)
            ->whereNull('o.deleted_at')
            ->value('o.id');

        if ($existing) {
            return (int) $existing;
        }

        $now = Carbon::now();
        $optionId = DB::table('option')->insertGetId([
            'type'       => $type,
            'role'       => $role,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('option_description')->insert([
            'option_id'     => $optionId,
            'language_code' => 'vi',
            'name'          => $name,
            'name_display'  => $name,
        ]);

        return (int) $optionId;
    }

    /**
     * Tìm option_value theo (option_id, description.name); nếu chưa có thì tạo.
     */
    private function upsertOptionValue(int $optionId, string $name, ?string $image): int
    {
        $existing = DB::table('option_value as ov')
            ->join('option_value_description as ovd', 'ovd.option_value_id', '=', 'ov.id')
            ->where('ov.option_id', $optionId)
            ->where('ovd.name', $name)
            ->value('ov.id');

        if ($existing) {
            return (int) $existing;
        }

        $now = Carbon::now();
        $valueId = DB::table('option_value')->insertGetId([
            'option_id'  => $optionId,
            'image'      => $image,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('option_value_description')->insert([
            'option_value_id' => $valueId,
            'language_code'   => 'vi',
            'name'            => $name,
        ]);

        return (int) $valueId;
    }

    /**
     * Xóa toàn bộ cluster variant + declaration product_option của 2 seed
     * options. KHÔNG đụng product_option của option khác (CMS thật).
     */
    private function truncateClusters(int $colorOptionId, int $sizeOptionId): void
    {
        $tables = [
            'stock_movement',
            'product_stock',
            'product_variant_attribute',
            'product_variant_description',
            'product_variant_special',
            'product_variant',
        ];
        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl)) {
                DB::table($tbl)->truncate();
                $this->line("  truncated {$tbl}");
            }
        }

        $deleted = DB::table('product_option')
            ->whereIn('option_id', [$colorOptionId, $sizeOptionId])
            ->delete();
        $this->line("  xóa {$deleted} product_option (seed declarations)");

        // Reset cờ aggregate trên product để backfill tính lại từ đầu.
        DB::table('product')->update([
            'has_variants'                 => 0,
            'min_variant_price'            => null,
            'max_variant_price'            => null,
            'max_variant_discount_percent' => null,
        ]);
    }

    /**
     * Build payload cho 1 chunk product. Trả 4 mảng + danh sách product_id đã
     * touch (để caller cộng dồn cho backfill). Hướng B: không còn trả mảng
     * product_option (variant declaration) — trục biến thể suy từ attribute.
     *
     * @return array{0:array, 1:array, 2:array, 3:array, 4:array<int>}
     */
    private function buildBatch(
        $products,
        int $percent,
        int $min,
        int $max,
        int $colorOptionId,
        int $sizeOptionId,
        array $colorValueIds,
        array $sizeValueIds,
        array $imageByColorValueId,
        int $startVariantId,
        int $startStockId,
        int $startMovementId,
    ): array {
        $now = Carbon::now();
        $variants = $attributes = $stocks = $movements = $variantSpecials = [];
        $touched = [];
        $specialPercent = (int) $this->option('special-percent');

        $variantId  = $startVariantId;
        $stockId    = $startStockId;
        $movementId = $startMovementId;
        // Pulled once per batch from core.stock.default_warehouse_id so the
        // seed payload follows the same single source of truth as runtime
        // callers (ProductVariant::productStock, CreateOrderService, ...).
        $warehouseId = (int) getCoreConfig('stock.default_warehouse_id');

        foreach ($products as $product) {
            if (rand(1, 100) > $percent) {
                continue;
            }

            // --full: lấy mọi tổ hợp (tránh dead-end UX "Hết hàng" giả). Mặc định
            // ngược lại pick random subset cho phép test data drift / out-of-stock.
            $count  = $this->option('full')
                ? (count($colorValueIds) * count($sizeValueIds))
                : rand($min, $max);
            $combos = $this->pickCombinations($colorValueIds, $sizeValueIds, $count);
            if (empty($combos)) {
                continue;
            }

            $touched[] = (int) $product->id;

            // Hướng B: KHÔNG còn tạo product_option role=variant. Trục biến thể
            // (kể cả thứ tự Color trước Size) suy từ product_variant_attribute +
            // option.sort_order — xem ProductOptionService::buildVariantOptions.

            $basePrice = (float) $this->randomBasePrice();
            $isFirst = true;
            $idx = 0;
            $defaultVariantGetsSpecial = $specialPercent > 0 && rand(1, 100) <= $specialPercent;

            foreach ($combos as [$colorValueId, $sizeValueId]) {
                $sig = $this->signature([
                    $colorOptionId => $colorValueId,
                    $sizeOptionId  => $sizeValueId,
                ]);

                // price: random ±15% base, clamp 1k chống giá âm/0.
                // regular_price: 10–50% cao hơn price → discount badge -9% đến -33%
                // realistic. Khoảng 20% variant set regular = price (không sale)
                // để test case struck + badge ẩn.
                $variantPrice = max(1000, (int) round($basePrice * (rand(85, 115) / 100)));
                $regularPrice = rand(1, 100) <= 20
                    ? $variantPrice
                    : (int) round($variantPrice * (rand(110, 150) / 100));
                $onHand = rand(1, 200);

                $variants[] = [
                    'id'                  => $variantId,
                    'product_id'          => $product->id,
                    'sku'                 => 'V-' . $product->id . '-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT),
                    'attribute_signature' => $sig,
                    'price'               => $variantPrice,
                    'regular_price'       => $regularPrice,
                    'points'              => 0,
                    'weight'              => null,
                    'image'               => $imageByColorValueId[$colorValueId],
                    'is_default'          => $isFirst ? 1 : 0,
                    'sort_order'          => $idx,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                    'deleted_at'          => null,
                ];

                $attributes[] = [
                    'product_variant_id' => $variantId,
                    'option_id'          => $colorOptionId,
                    'option_value_id'    => $colorValueId,
                ];
                $attributes[] = [
                    'product_variant_id' => $variantId,
                    'option_id'          => $sizeOptionId,
                    'option_value_id'    => $sizeValueId,
                ];

                $stocks[] = [
                    'id'                 => $stockId,
                    'product_variant_id' => $variantId,
                    'warehouse_id'       => $warehouseId,
                    'on_hand'            => $onHand,
                    'reserved'           => 0,
                    'subtract'           => 1,
                    'version'            => 0,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];

                $movements[] = [
                    'id'                 => $movementId,
                    'product_variant_id' => $variantId,
                    'warehouse_id'       => $warehouseId,
                    'type'               => 'receive',
                    'quantity_change'    => $onHand,
                    'on_hand_after'      => $onHand,
                    'reference_type'     => 'seed',
                    'reference_id'       => null,
                    'user_id'            => null,
                    'note'               => 'Seeded via variants:seed',
                    'created_at'         => $now,
                ];

                if ($isFirst && $defaultVariantGetsSpecial) {
                    $variantSpecials[] = [
                        'product_variant_id' => $variantId,
                        'product_id'         => $product->id,
                        'user_group_id'      => 1,
                        'priority'           => rand(1, 10),
                        'price'              => max(1000, (int) round($variantPrice * (rand(50, 90) / 100))),
                        'date_start'         => rand(0, 1) ? $now->copy()->subDays(rand(0, 30)) : null,
                        'date_end'           => rand(0, 1) ? $now->copy()->addDays(rand(1, 60)) : null,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                        'deleted_at'         => null,
                    ];
                }

                $variantId++;
                $stockId++;
                $movementId++;
                $isFirst = false;
                $idx++;
            }
        }

        return [$variants, $attributes, $stocks, $movements, $variantSpecials, $touched];
    }

    private function randomBasePrice(): int
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

    /**
     * Chọn $needed tổ hợp (color, size) không trùng nhau từ full grid C × S.
     * Total grid = 6 × 5 = 30, đủ rộng cho 2–4 variant/product.
     *
     * @return array<int, array{0:int, 1:int}>
     */
    private function pickCombinations(array $colorIds, array $sizeIds, int $needed): array
    {
        $grid = [];
        foreach ($colorIds as $c) {
            foreach ($sizeIds as $s) {
                $grid[] = [$c, $s];
            }
        }
        shuffle($grid);
        return array_slice($grid, 0, min($needed, count($grid)));
    }

    /**
     * Delegate sang nguồn sự thật duy nhất ProductVariant::buildAttributeSignature()
     * — KHÔNG copy lại công thức (tránh drift với CartService::resolveVariantId
     * và migration 100007).
     */
    private function signature(array $optionToValue): string
    {
        return ProductVariant::buildAttributeSignature($optionToValue);
    }

    /**
     * Cập nhật product.has_variants + min/max_variant_price bằng GROUP BY thay
     * vì update từng row trong PHP — chuyển aggregate sang MySQL, nhanh hơn
     * nhiều cho 50k product.
     */
    private function backfillProductAggregates(): void
    {
        DB::statement('
            UPDATE product
            SET has_variants = 1
            WHERE id IN (SELECT DISTINCT product_id FROM product_variant WHERE deleted_at IS NULL)
        ');

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
        ');

        // MAX discount %: tính trên những variant có regular_price > price.
        // GREATEST nhằm phòng trường hợp data lỗi (regular < price) — bỏ qua
        // bằng FLOOR(0). Round half-up cho khớp UI.
        DB::statement('
            UPDATE product p
            INNER JOIN (
                SELECT product_id,
                       MAX(FLOOR((regular_price - price) / regular_price * 100)) AS pct
                FROM product_variant
                WHERE deleted_at IS NULL
                  AND regular_price IS NOT NULL
                  AND regular_price > price
                GROUP BY product_id
            ) d ON d.product_id = p.id
            SET p.max_variant_discount_percent = d.pct
        ');
    }
}
