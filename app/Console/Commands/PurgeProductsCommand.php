<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Truncate sạch toàn bộ cluster product + reset AUTO_INCREMENT.
 *
 * Tại sao có command riêng?
 * -------------------------
 * Các `--truncate` flag trong products:seed / variants:seed / specials:seed
 * gắn liền với bước seed → chỉ truncate những bảng từng command quan tâm,
 * và LUÔN seed lại ngay sau đó. Khi muốn "chỉ xoá, không seed gì cả"
 * (vd để export DB rỗng, hoặc reset trước khi import dump thật), cần
 * command đứng riêng.
 *
 * Cách dùng:
 *   php artisan products:purge                  # confirm trước khi xoá
 *   php artisan products:purge --force          # không hỏi (cho CI/script)
 *   php artisan products:purge --keep-taxonomy  # giữ category + manufacturer + filter
 *
 * Sau khi purge:
 *   php artisan products:seed-all --total=500000   # seed lại sạch sẽ
 */
class PurgeProductsCommand extends Command
{
    protected $signature = 'products:purge
        {--force : Bỏ qua confirm (dùng cho CI/script)}
        {--keep-taxonomy : Giữ lại category, manufacturer, filter, option (chỉ xoá product cluster)}
        {--keep-options : Giữ lại option/option_value/option_description (vd để giữ Color, Size seed)}';

    protected $description = 'Truncate sạch product cluster + reset AUTO_INCREMENT. KHÔNG seed lại — chỉ xoá.';

    /**
     * Thứ tự không quan trọng vì foreign_key_checks off; nhưng group theo
     * cluster cho dễ đọc / dễ thêm bảng mới.
     */
    private const CLUSTER_TABLES = [
        // Variant cluster (Shopify-style stock pipeline)
        'stock_movement',
        'product_stock',
        'product_variant_attribute',
        'product_variant_description',
        'product_variant_special',
        'product_variant',

        // Cũ — schema legacy OpenCart, có thể không còn dùng nhưng cứ truncate
        'product_option_value',
        'product_option',

        // Product satellite tables
        'product_image',
        'product_filter',
        'product_category',
        'product_attribute',
        'product_discount',
        'product_related',
        'product_ingredient',
        'product_reward',
        'product_draft',

        // Bảng chính
        'product_description',
        'product',
    ];

    private const TAXONOMY_TABLES = [
        'category_description',
        'category',
        'manufacturer',
        'filter_value',
        'filter',
    ];

    /** Option seed (Color, Size, custom field) tạo bởi variants:seed. */
    private const OPTION_TABLES = [
        'option_value_description',
        'option_value',
        'option_description',
        'option',
    ];

    public function handle(): int
    {
        $force        = (bool) $this->option('force');
        $keepTaxonomy = (bool) $this->option('keep-taxonomy');
        $keepOptions  = (bool) $this->option('keep-options');

        $tables = self::CLUSTER_TABLES;
        if (! $keepTaxonomy) {
            $tables = array_merge($tables, self::TAXONOMY_TABLES);
        }
        if (! $keepOptions) {
            $tables = array_merge($tables, self::OPTION_TABLES);
        }

        // Đếm trước cho user thấy mức tàn sát.
        $counts = [];
        $totalRows = 0;
        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            $n = (int) DB::table($tbl)->count();
            if ($n > 0) {
                $counts[$tbl] = $n;
                $totalRows += $n;
            }
        }

        if ($totalRows === 0) {
            $this->info('Tất cả bảng đã rỗng — không có gì để purge.');
            return self::SUCCESS;
        }

        $this->warn("Sắp TRUNCATE " . count($counts) . ' bảng (' . number_format($totalRows) . ' row tổng):');
        foreach ($counts as $tbl => $n) {
            $this->line(sprintf('  %-32s %12s', $tbl, number_format($n)));
        }

        if (! $force && ! $this->confirm('Tiếp tục? Hành động KHÔNG thể hoàn tác.', false)) {
            $this->info('Huỷ.');
            return self::FAILURE;
        }

        $started = microtime(true);

        DB::statement('SET foreign_key_checks=0');
        DB::statement('SET unique_checks=0');

        try {
            foreach ($tables as $tbl) {
                if (! Schema::hasTable($tbl)) {
                    continue;
                }
                DB::table($tbl)->truncate();
                // TRUNCATE đã reset AUTO_INCREMENT về 1 trên MySQL/MariaDB,
                // nhưng set lại tường minh cho an toàn (vài config InnoDB
                // không reset).
                DB::statement("ALTER TABLE `{$tbl}` AUTO_INCREMENT = 1");
                $this->line("  truncated  {$tbl}");
            }

            // Reset cờ aggregate trên product (nếu --keep-taxonomy giữ row product
            // nào đó — về lý thuyết product đã bị truncate ở trên rồi).
            if (Schema::hasColumn('product', 'has_variants')) {
                DB::table('product')->update([
                    'has_variants'                 => 0,
                    'min_variant_price'            => null,
                    'max_variant_price'            => null,
                    'max_variant_discount_percent' => null,
                ]);
            }
        } finally {
            DB::statement('SET foreign_key_checks=1');
            DB::statement('SET unique_checks=1');
        }

        $elapsed = round(microtime(true) - $started, 2);
        $this->info("Xong: purge " . number_format($totalRows) . " row trong {$elapsed}s.");

        if (! config('scout.driver') || config('scout.driver') === 'null') {
            return self::SUCCESS;
        }

        // Nhắc flush Meilisearch index — Scout không tự dọn khi DB bị truncate raw.
        $this->newLine();
        $this->warn('Meilisearch index "products" vẫn còn data cũ. Flush bằng:');
        $this->line('  php artisan scout:flush "App\\Models\\Entities\\Product"');

        return self::SUCCESS;
    }
}
