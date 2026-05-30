<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_variant_description: i18n cho label tự đặt của variant.
 *
 * Trường hợp dùng: admin muốn ghi đè label thay vì ghép tự động từ
 * option_value names (vd "Size 38 — vừa chân nhỏ"). Optional: variant nào
 * không có row tương ứng thì frontend tự build từ option_value.description.
 *
 * Composite PK (product_variant_id, language_code) — convention dự án (xem
 * product_description, blog_description). forLocale() scope tự áp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_description', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id');
            $table->string('language_code', 5);
            $table->string('label', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->primary(['product_variant_id', 'language_code'], 'pk_product_variant_description');

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variant')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_description');
    }
};
