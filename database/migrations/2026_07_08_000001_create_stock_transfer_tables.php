<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển kho — StockMovementType::Transfer đã khai từ trước nhưng chưa có
 * bảng chứng từ. Thiết kế 2 tầng:
 *
 *   stock_transfer       phiếu chuyển: kho đi / kho đến / trạng thái
 *   stock_transfer_item  dòng hàng: variant + số lượng
 *
 * Ghi sổ tồn khi CHUYỂN TRẠNG THÁI (service lo, trong transaction):
 *   Draft → InTransit : trừ on_hand kho đi, movement type=transfer (âm),
 *                       reference_type='stock_transfer'.
 *   InTransit → Received: cộng on_hand kho đến, movement type=transfer (dương).
 *   → Hàng "trên đường" không nằm trong tồn kho nào — đúng thực tế, tổng
 *     movement vẫn cân (âm kho đi + dương kho đến = 0).
 *
 * received_quantity trên item: nhận thiếu/hỏng ghi số thật, chênh lệch xử lý
 * bằng movement type=adjust ở kho đến.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('stock_transfer')) {
            Schema::create('stock_transfer', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->unsignedBigInteger('from_warehouse_id');
                $table->unsignedBigInteger('to_warehouse_id');
                $table->string('status', 16)->default('draft');
                $table->string('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->foreign('from_warehouse_id', 'fk_st_from_warehouse')
                    ->references('id')->on('warehouse')->restrictOnDelete();
                $table->foreign('to_warehouse_id', 'fk_st_to_warehouse')
                    ->references('id')->on('warehouse')->restrictOnDelete();

                $table->index(['status', 'created_at'], 'idx_st_status_time');
                $table->index(['from_warehouse_id', 'status'], 'idx_st_from_status');
                $table->index(['to_warehouse_id', 'status'], 'idx_st_to_status');
            });
        }

        if (! Schema::hasTable('stock_transfer_item')) {
            Schema::create('stock_transfer_item', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_id');
                $table->unsignedBigInteger('product_variant_id');
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('received_quantity')->nullable();
                $table->timestamps();

                $table->foreign('stock_transfer_id', 'fk_sti_transfer')
                    ->references('id')->on('stock_transfer')->cascadeOnDelete();
                $table->foreign('product_variant_id', 'fk_sti_variant')
                    ->references('id')->on('product_variant')->restrictOnDelete();

                // 1 variant chỉ 1 dòng / phiếu — gộp số lượng thay vì thêm dòng.
                $table->unique(['stock_transfer_id', 'product_variant_id'], 'uq_sti_transfer_variant');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_item');
        Schema::dropIfExists('stock_transfer');
    }
};
