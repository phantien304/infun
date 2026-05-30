<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_variant_attribute: pivot many-to-many giữa product_variant và
 * option_value.
 *
 * 1 variant gồm n option (vd Size + Color + Material), mỗi option có đúng 1
 * option_value tham gia. PRIMARY KEY (product_variant_id, option_id) enforce
 * điều này ở DB layer — không variant nào có 2 Size trong cùng tổ hợp.
 *
 * KHÔNG copy product_id sang đây — pivot chain qua product_variant_id để
 * tránh transitive dependency (3NF). Mọi join về product đi qua
 * product_variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_attribute', function (Blueprint $table) {
            // product_variant.id là bảng mới (BIGINT UNSIGNED).
            $table->unsignedBigInteger('product_variant_id');
            // option.id, option_value.id legacy INT(11) SIGNED.
            $table->integer('option_id');
            $table->integer('option_value_id');

            $table->primary(['product_variant_id', 'option_id'], 'pk_product_variant_attribute');

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variant')
                ->cascadeOnDelete();
            $table->foreign('option_id')
                ->references('id')->on('option')
                ->restrictOnDelete();
            $table->foreign('option_value_id')
                ->references('id')->on('option_value')
                ->restrictOnDelete();

            $table->index(['option_id', 'option_value_id'], 'idx_product_variant_attribute_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute');
    }
};
