<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột `orders_product.product_variant_id` để link mỗi dòng order ↔ 1
 * variant cụ thể (Size=M, Color=Red).
 *
 * Type: UNSIGNED BIGINT — match CHÍNH XÁC `product_variant.id` (bigIncrements).
 * Convention legacy "INT signed cho cột FK tới bảng cũ" KHÔNG áp dụng ở đây
 * vì variant là bảng mới Laravel modern, không phải bảng OpenCart.
 *
 * Nullable: bắt buộc — (a) row order cũ trước migration không có variant
 * info; (b) simple product (has_variants = false) không có variant. NULL
 * không phá FK vì MySQL skip check cho NULL.
 *
 * ON DELETE: RESTRICT — không cho xoá variant nếu còn order ref. Order
 * history là source of truth tài chính, không được mất link.
 *
 * Index riêng trên FK column để stock reconciliation query
 * (`SELECT SUM(quantity) FROM orders_product WHERE product_variant_id = ?`)
 * không full scan. Laravel `->foreign()` không tự tạo index đáng tin trên
 * MariaDB → khai báo `->index()` explicit.
 *
 * Sau migration: gỡ comment marker trong
 * `App\Services\Checkout\CreateOrderService::writeOrderItems`, thêm lại:
 *   'product_variant_id' => $item['product_variant_id'] ?? null,
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders_product')) {
            return;
        }
        if (Schema::hasColumn('orders_product', 'product_variant_id')) {
            return;
        }

        // Bước 1: thêm cột + index. Tách khỏi FK để xử lý 2 lỗi rời:
        // dirty data (variant_id không tồn tại) sẽ làm FK fail, mà column
        // đã add rồi sẽ rollback khó. Add column trước, dọn data, mới FK.
        Schema::table('orders_product', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')
                ->nullable()
                ->after('product_id');
            $table->index('product_variant_id', 'idx_orders_product_variant_id');
        });

        // Bước 2: dọn orphan trước khi gắn FK — nếu có row trỏ tới
        // product_variant.id không tồn tại (data corruption / variant đã
        // hard delete) thì set NULL để FK gắn được.
        DB::statement('
            UPDATE orders_product op
            LEFT JOIN product_variant pv ON pv.id = op.product_variant_id
            SET op.product_variant_id = NULL
            WHERE op.product_variant_id IS NOT NULL
              AND pv.id IS NULL
        ');

        // Bước 3: gắn FK. RESTRICT giữ order history nguyên vẹn — variant
        // không xoá được nếu còn ref. Soft delete ở variant không trigger FK.
        Schema::table('orders_product', function (Blueprint $table) {
            $table->foreign('product_variant_id', 'fk_orders_product_variant')
                ->references('id')
                ->on('product_variant')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders_product')) {
            return;
        }
        if (! Schema::hasColumn('orders_product', 'product_variant_id')) {
            return;
        }

        Schema::table('orders_product', function (Blueprint $table) {
            $table->dropForeign('fk_orders_product_variant');
            $table->dropIndex('idx_orders_product_variant_id');
            $table->dropColumn('product_variant_id');
        });
    }
};
