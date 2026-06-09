<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_variant_special — campaign giảm giá ở mức variant (mirror
 * product_special). Lý do tách bảng (Hướng B, xem CLAUDE.md):
 *
 *  - `product_variant.price` đã là giá tuyệt đối per-SKU; `regular_price` chỉ
 *    là MSRP tĩnh để gạch ngang. KHÔNG có cơ chế nào lo time-bound + user
 *    group + priority cho variant — đó là việc của bảng này.
 *  - product_special vẫn giữ cho simple product (has_variants=0). Variant
 *    product KHÔNG còn áp product_special (DTO::formatPrice đã skip;
 *    effectivePriceExpression cũng sẽ skip ở nhánh variant).
 *
 * Convention chốt:
 *  - product_id denormalize từ product_variant.product_id để backfill
 *    aggregate (min/max effective variant price) bằng GROUP BY mà không cần
 *    join — observer cần insert đúng giá trị này khi save variant_special.
 *  - Index lookup khớp pattern truy vấn của relation `variantSpecial()`:
 *    WHERE product_variant_id = ? AND user_group_id = ? AND date_start <= ?
 *    AND date_end >= ? ORDER BY priority DESC LIMIT 1.
 *  - FK product_variant_id CASCADE: xoá variant tự dọn special; FK product_id
 *    CASCADE đồng bộ vòng đời tổng.
 *  - Soft delete để audit campaign quá khứ (UBCKNN / kế toán cần truy ngược).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_special', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_variant_id');
            // FK tới product.id legacy là INT SIGNED (xem CLAUDE.md
            // "Convention DB / migration" — FK column khớp chính xác type).
            $table->integer('product_id')
                ->comment('Denormalize từ product_variant.product_id; backfill aggregate GROUP BY product');
            $table->unsignedInteger('user_group_id')->default(1);
            $table->integer('priority')->default(0)
                ->comment('Cao nhất thắng khi nhiều special overlap');
            $table->decimal('price', 15, 2)
                ->comment('Giá tuyệt đối khi campaign active — KHÔNG phải delta');
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variant')
                ->cascadeOnDelete();
            $table->foreign('product_id')
                ->references('id')->on('product')
                ->cascadeOnDelete();

            // Covering index cho relation variantSpecial() (xem
            // ProductVariant::variantSpecial). Khớp pattern WHERE+ORDER BY.
            $table->index(
                ['product_variant_id', 'user_group_id', 'priority', 'date_start', 'date_end'],
                'idx_pvs_lookup'
            );
            // Hỗ trợ backfill aggregate min/max effective theo product.
            $table->index(['product_id', 'user_group_id'], 'idx_pvs_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_special');
    }
};
