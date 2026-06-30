<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostic: in cột thật của bảng `product` + so với danh sách kỳ vọng.
 *
 * Mục đích: tránh seed/insert vỡ runtime vì migration đã drop cột nhưng
 * code seed cũ vẫn reference (vd `product.video`, `product.rating`,
 * `product.link_sale_custom`).
 *
 * Cách dùng:
 *   php artisan products:schema-check
 *
 * Output mẫu (sau khi drop_price + drop_legacy_quantity_subtract):
 *   [✓] Hiện diện : 28 cột
 *   [×] ĐÃ DROP   : price, quantity, subtract, video, rating, total_rating
 *   [?] LEGACY    : tax_class_id, upc, ean, jan, isbn, mpn, location
 *   [+] BỔ SUNG   : has_variants, min_variant_price, max_variant_price,
 *                   max_variant_discount_percent, review_count, rating_avg, …
 */
class CheckProductSchemaCommand extends Command
{
    protected $signature = 'products:schema-check {table=product : Tên bảng cần check (mặc định product)}';

    protected $description = 'In cột thật của bảng product + cảnh báo cột nghi đã drop / mới thêm. Chạy trước khi seed nếu nghi ngờ schema drift.';

    /**
     * Authoritative — copy từ ProductWriteService::FLAT_FIELDS + aggregate
     * mới. Đây là cột mà CMS HIỆN TẠI thực sự ghi.
     */
    private const EXPECTED_PRODUCT_COLUMNS = [
        // Identity + core
        'id', 'model', 'sku', 'image', 'badge', 'sort_order',
        // Catalog metadata
        'manufacturer_id', 'stock_status_id', 'date_available',
        // Physical
        'weight', 'weight_class_id', 'length', 'width', 'height', 'length_class_id',
        // Sales
        'tax_class_id', 'shipping', 'minimum', 'points', 'link_sale', 'link_sale_custom',
        // Legacy identifiers (vẫn giữ trong FLAT_FIELDS)
        'upc', 'ean', 'jan', 'isbn', 'mpn', 'location',
        // Behaviour flags
        'is_add_cart', 'is_custom', 'is_review', 'subtract',
        // Aggregate denormalized (variant cluster)
        'has_variants', 'min_variant_price', 'max_variant_price', 'max_variant_discount_percent',
        // Aggregate denormalized (review cluster)
        'review_count', 'rating_avg', 'rating_sum', 'rating_distribution', 'rating_updated_at',
        // Behaviour misc (vẫn còn)
        'viewed',
        // Timestamps + soft delete
        'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Cột đã DROP khỏi schema. Cảnh báo nếu DB còn cột này (migration chưa
     * chạy hoặc rollback nhầm).
     */
    private const HARD_DROPPED = [
        'price'        => 'Drop bởi 2026_06_18_000001 → đọc qua $product->defaultVariant?->price',
        'quantity'     => 'Drop legacy → on_hand thuộc product_stock (xem unify_simple_product_stock)',
        'subtract'     => 'Drop legacy → policy thuộc product_stock.inventory_policy',
        'rating'       => 'Drop legacy → thay bởi product.rating_avg',
        'total_rating' => 'Drop legacy → thay bởi product.rating_sum / review_count',
        'video'        => 'Drop legacy → multimedia chuyển sang product_image hoặc review_media',
    ];

    /** Cột legacy nghi đã bị drop manual (không có migration UP, không reproducible). */
    private const SOFT_DROPPED_CANDIDATES = [
        'link_sale_custom',
    ];

    public function handle(): int
    {
        $table = $this->argument('table');

        if (! Schema::hasTable($table)) {
            $this->error("Bảng `{$table}` không tồn tại.");
            return self::FAILURE;
        }

        $actual = collect(Schema::getColumnListing($table))->sort()->values()->all();
        $expected = collect(self::EXPECTED_PRODUCT_COLUMNS)->sort()->values()->all();

        $present  = array_intersect($actual, $expected);
        $missing  = array_diff($expected, $actual);
        $extra    = array_diff($actual, $expected);

        $this->line("Bảng: <fg=cyan>{$table}</> — <fg=green>" . count($actual) . " cột thực tế</>");
        $this->newLine();

        // 1. Hard-drop alert
        $hardDropHit = array_intersect(array_keys(self::HARD_DROPPED), $actual);
        $hardDropMiss = array_diff(array_keys(self::HARD_DROPPED), $actual);
        foreach ($hardDropHit as $col) {
            $this->warn("[!!] Cột `{$col}` LẼ RA đã drop nhưng vẫn còn trong DB → migration `drop_{$col}_from_product` chưa chạy?");
        }
        foreach ($hardDropMiss as $col) {
            $this->line("[✓] Hard-drop confirmed: `{$col}` — " . self::HARD_DROPPED[$col]);
        }
        $this->newLine();

        // 2. Cột legacy nghi đã drop manual
        $softDroppedFound = array_intersect(self::SOFT_DROPPED_CANDIDATES, $missing);
        if ($softDroppedFound) {
            $this->warn('Cột LEGACY đã DROP (nghi vấn ban đầu, không có migration UP):');
            foreach ($softDroppedFound as $col) {
                $this->line("  [×] {$col}");
            }
            $this->newLine();
        }

        // 3. Missing — cột code expect mà DB không có
        $missingNonLegacy = array_diff($missing, self::SOFT_DROPPED_CANDIDATES, array_keys(self::HARD_DROPPED));
        if ($missingNonLegacy) {
            $this->error('Cột code expect nhưng DB KHÔNG có (sẽ vỡ insert):');
            foreach ($missingNonLegacy as $col) {
                $this->line("  [?] {$col}");
            }
            $this->newLine();
        }

        // 4. Extra — DB có nhưng code không quan tâm (info)
        if ($extra) {
            $this->comment('Cột tồn tại trong DB nhưng code mới không quản (có thể là legacy còn dư hoặc cột mới chưa update danh sách EXPECTED):');
            foreach ($extra as $col) {
                $this->line("  [+] {$col}");
            }
            $this->newLine();
        }

        // 5. Row count quick
        $rowCount = (int) DB::table($table)->count();
        $this->line("Row count: <fg=cyan>" . number_format($rowCount) . "</>");

        // 6. Tip cho seed
        $this->newLine();
        $this->info('Tham khảo:');
        $this->line('  - Truncate sạch:  php artisan products:purge');
        $this->line('  - Seed 500k mix:  php artisan products:seed-all');
        $this->line('  - Nếu thấy "Cột code expect nhưng DB KHÔNG có" → cần migration bù hoặc sửa SeedProductsCommand bỏ cột.');

        return self::SUCCESS;
    }
}
