<?php

/**
 * Index perf cho list 500k product — phương án TỐI THIỂU (4 index thay vì 8).
 *
 * NGUYÊN TẮC:
 *  - Composite > nhiều single-column (1 B-tree update / row cover được
 *    nhiều query plan).
 *  - Bỏ index trên cột low-cardinality độc lập (deleted_at, date_available)
 *    — planner không pick, chỉ tốn write.
 *  - Cột ORDER BY luôn đặt SAU equality column trong composite để
 *    backward-scan + LIMIT sớm dừng (không cần filesort).
 *
 * HOT QUERY hỗ trợ:
 *  - Trang list default:
 *      WHERE deleted_at IS NULL AND (date_available <= NOW() OR IS NULL)
 *      ORDER BY created_at DESC LIMIT 20
 *      → dùng idx_product_list (deleted_at, created_at)
 *      → prefix deleted_at=NULL → backward scan created_at → LIMIT dừng sớm
 *      → date_available filter ở row-data, OK vì LIMIT nhỏ + selectivity cao.
 *  - Filter manufacturer:
 *      WHERE manufacturer_id IN (..) AND deleted_at IS NULL ORDER BY created_at DESC
 *      → dùng idx_product_mfr (manufacturer_id, created_at)
 *  - whereHas('productCategories'):
 *      EXISTS (SELECT 1 FROM product_category WHERE category_id IN (..)
 *              AND product_id = product.id)
 *      → dùng idx_pc_lookup (category_id, product_id)  -- leftmost = category_id (filter), rightmost = product_id (join)
 *  - whereHas('productFilters'): tương tự → idx_pf_lookup (filter_value_id, product_id)
 *
 * COST:
 *  - INSERT/UPDATE: +200µs / row (không cảm nhận được ở CMS).
 *  - Storage: +50MB cho 500k product. Bé so với buffer pool 1G.
 *
 * KHÔNG add:
 *  - product_description: PK convention OpenCart đã là (product_id, language_code).
 *  - product.deleted_at đơn: cardinality 2 giá trị, planner không pick.
 *  - product.date_available đơn: cardinality thấp.
 *  - product.sort_order: trang chính sort theo created_at; legacy sort
 *    sort_order vẫn nhanh nhờ scan composite_list rồi filter (LIMIT nhỏ).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. product — composite cho hot path list mặc định + filter manufacturer
        $this->addIndexIfMissing('product', 'idx_product_list', ['deleted_at', 'created_at']);
        $this->addIndexIfMissing('product', 'idx_product_mfr', ['manufacturer_id', 'created_at']);

        // 2. product_filter — whereHas filter_value_id
        if (Schema::hasTable('product_filter')) {
            $this->addIndexIfMissing('product_filter', 'idx_pf_lookup', ['filter_value_id', 'product_id']);
        }

        // 3. product_category — whereHas category_id
        if (Schema::hasTable('product_category')) {
            $this->addIndexIfMissing('product_category', 'idx_pc_lookup', ['category_id', 'product_id']);
        }

        // ANALYZE: cập nhật stats để planner pick đúng index. Migration đã apply
        // schema nhưng planner stats vẫn còn snapshot cũ — không ANALYZE thì
        // có khi index mới bị skip ở vài giờ đầu.
        foreach (['product', 'product_filter', 'product_category'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                DB::statement("ANALYZE TABLE `{$tbl}`");
            }
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('product', 'idx_product_list');
        $this->dropIndexIfExists('product', 'idx_product_mfr');
        $this->dropIndexIfExists('product_filter', 'idx_pf_lookup');
        $this->dropIndexIfExists('product_category', 'idx_pc_lookup');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
        if ($exists) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->index($columns, $indexName));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
        if (! $exists) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->dropIndex($indexName));
    }
};
