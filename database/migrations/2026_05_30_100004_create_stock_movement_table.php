<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_movement: append-only audit log mọi thay đổi tồn kho.
 *
 * Mỗi nhập/xuất/reserve/release là 1 row. KHÔNG update — chỉ insert. Trở thành
 * source of truth để rebuild on_hand khi nghi ngờ data corruption:
 *   on_hand = SUM(quantity_change) WHERE product_variant_id=? AND warehouse_id=?
 *
 * type: 'receive' (nhập kho), 'sale' (bán), 'reserve' (cart hold), 'release'
 * (cart cancel/expire), 'adjust' (chỉnh tay), 'transfer_in/_out' (chuyển kho).
 *
 * reference_type/reference_id: polymorphic — order, cart, stocktake... Để truy
 * ngược nguồn gốc 1 movement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('warehouse_id')->default(1);
            $table->string('type', 32);
            $table->integer('quantity_change')
                ->comment('Signed: dương = nhập, âm = xuất');
            $table->integer('on_hand_after')->nullable()
                ->comment('Snapshot sau khi áp dụng — đỡ phải SUM lại khi audit');
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('Ai gây ra movement này');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variant')
                ->restrictOnDelete();

            $table->index(['product_variant_id', 'warehouse_id', 'created_at'], 'idx_stock_movement_variant_time');
            $table->index(['reference_type', 'reference_id'], 'idx_stock_movement_reference');
            $table->index(['type', 'created_at'], 'idx_stock_movement_type_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement');
    }
};
