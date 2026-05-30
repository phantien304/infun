<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_option_definition: khai báo tập option BẮT BUỘC của 1 product.
 *
 * Enforce invariant "mọi variant của cùng product có cùng tập option_id".
 * Vd: áo có Size + Color → mọi variant phải gồm đúng 1 Size và đúng 1 Color,
 * không variant nào chỉ có Size.
 *
 * Application layer khi insert variant phải:
 *   1. Đọc danh sách option_id từ bảng này theo product_id
 *   2. Đảm bảo pivot product_variant_attribute của variant mới khớp tập đó
 *
 * Có thể tăng cường bằng trigger nếu cần cưỡng chế tuyệt đối ở DB.
 *
 * is_required = false dành cho option phụ (vd "Engraving text") có thể bỏ
 * qua trong 1 số variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_definition', function (Blueprint $table) {
            // product.id và option.id legacy INT(11) SIGNED.
            $table->integer('product_id');
            $table->integer('option_id');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['product_id', 'option_id'], 'pk_product_option_definition');

            $table->foreign('product_id')
                ->references('id')->on('product')
                ->cascadeOnDelete();
            $table->foreign('option_id')
                ->references('id')->on('option')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_definition');
    }
};
