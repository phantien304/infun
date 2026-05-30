<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_variant: thay thế cụm product_option_value + product_option_value_2.
 *
 * 1 row = 1 tổ hợp variant cụ thể (vd Size=M + Color=Red), tự đứng độc lập,
 * mang giá tuyệt đối + SKU + ảnh + sort_order. Quantity tách sang product_stock
 * (xem migration kế tiếp).
 *
 * attribute_signature: hash các option_value_id đã sort theo option_id,
 * UNIQUE (product_id, attribute_signature) cưỡng chế "1 product không thể có
 * 2 variant trùng tổ hợp". Cập nhật từ application layer khi save pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant', function (Blueprint $table) {
            $table->bigIncrements('id');
            // product.id legacy là INT(11) SIGNED (OpenCart convention) —
            // FK column phải khớp chính xác type + signedness + size.
            $table->integer('product_id');
            $table->string('sku', 64)->nullable();
            $table->char('attribute_signature', 32)->nullable()
                ->comment('MD5 các option_value_id sort theo option_id; dedupe tổ hợp');
            $table->decimal('price', 15, 2)->default(0)
                ->comment('Giá tuyệt đối cuối cùng của variant — KHÔNG phải delta');
            $table->integer('points')->default(0);
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('image', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')
                ->references('id')->on('product')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'sku'], 'uq_product_variant_sku');
            $table->unique(['product_id', 'attribute_signature'], 'uq_product_variant_signature');
            $table->index('product_id', 'idx_product_variant_product');
            $table->index(['product_id', 'is_default'], 'idx_product_variant_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant');
    }
};
