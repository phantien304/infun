<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_reservation: giữ chỗ tồn kho ("hold") cho từng phiên checkout.
 *
 * Vì sao cần:
 *  - product_stock.reserved là con số denormalized tổng các hold đang active.
 *    Bảng này là nguồn chi tiết để biết AI đang giữ BAO NHIÊU và tới KHI NÀO,
 *    phục vụ việc tự động nhả (release) khi hết hạn.
 *  - sellable = on_hand - reserved → khách khác thấy tồn giảm ngay khi có hold,
 *    thu hẹp cửa sổ race khi flash sale.
 *
 * Vòng đời 1 hold:
 *  reserve  → tạo/juincrease row + product_stock.reserved += delta
 *  consume  → tạo đơn thành công: on_hand -= qty, reserved -= qty, xoá row
 *  release  → huỷ tay hoặc hết hạn (stock:release-expired): reserved -= qty, xoá row
 *
 * UNIQUE (holder, product_variant_id, warehouse_id): mỗi phiên giữ đúng 1 row
 * cho 1 variant/kho → reserve khi refresh trang là idempotent (upsert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('holder', 191)
                ->comment('Định danh phiên giữ chỗ — thường là session id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('warehouse_id')->default(1);
            $table->integer('quantity')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variant')
                ->cascadeOnDelete();

            $table->unique(
                ['holder', 'product_variant_id', 'warehouse_id'],
                'uq_stock_reservation_holder_variant_warehouse',
            );
            $table->index(['product_variant_id', 'warehouse_id'], 'idx_stock_reservation_variant_warehouse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservation');
    }
};
