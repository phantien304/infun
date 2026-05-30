<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_stock: tách tồn kho khỏi product_variant.
 *
 * Lý do tách:
 *  - quantity là dữ liệu vòng đời ngắn + concurrency cao (mỗi order modify)
 *    → để chung bảng variant gây lock contention với read trang chi tiết.
 *  - on_hand vs reserved: phân biệt "tồn thực tế" vs "đã gom cho cart pending"
 *    → tránh oversell. available = on_hand - reserved (có thể tính bằng
 *    generated column).
 *  - warehouse_id: hỗ trợ đa kho ngay từ đầu, kể cả khi chưa có nhu cầu chỉ
 *    cần seed 1 row warehouse_id=1.
 *  - version: optimistic locking — UPDATE ... WHERE id=? AND version=?
 *    (affected_rows=0 → retry). Đỡ phải SELECT FOR UPDATE.
 *
 * UNIQUE (product_variant_id, warehouse_id) — 1 variant có đúng 1 row stock
 * / kho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('warehouse_id')->default(1);
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->boolean('subtract')->default(true)
                ->comment('Cờ "track stock"; false = bán không trừ tồn');
            $table->unsignedBigInteger('version')->default(0)
                ->comment('Optimistic lock; tăng 1 sau mỗi UPDATE thành công');
            $table->timestamps();

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variant')
                ->cascadeOnDelete();

            $table->unique(['product_variant_id', 'warehouse_id'], 'uq_product_stock_variant_warehouse');
            $table->index(['warehouse_id', 'on_hand'], 'idx_product_stock_warehouse_qty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock');
    }
};
