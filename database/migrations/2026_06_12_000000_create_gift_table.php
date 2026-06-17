<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master gift — campaign tặng SP miễn phí khi đơn đủ điều kiện.
 *
 * Trigger type:
 *   1 = min_subtotal           → đơn từ X VND
 *   2 = buy_specific_product   → mua SP cụ thể (xem gift_trigger_product)
 *
 * Pick type — UX user chọn gift:
 *   0 = auto       → tự áp tất cả gift_item khi đủ ĐK (1 món auto add)
 *   1 = pick_1_of_n→ chọn 1 trong N option (radio)
 *   2 = pick_up_to_n → chọn tối đa N (checkbox), pick_limit cấu hình max
 *
 * Quota:
 *   uses_total NULL = unlimited; != NULL → so với used_count denormalize.
 *
 * KHÔNG hardcode literal — code đọc qua `getCoreConfig('gift.trigger_type.*')`
 * và `getCoreConfig('gift.pick_type.*')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gift')) {
            return;
        }

        Schema::create('gift', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 128);
            $table->text('description')->nullable();

            $table->tinyInteger('trigger_type')->unsigned();
            $table->decimal('min_subtotal', 15, 2)->nullable();

            $table->tinyInteger('pick_type')->unsigned()->default(0);
            $table->integer('pick_limit')->unsigned()->nullable();

            $table->integer('uses_total')->unsigned()->nullable();
            $table->integer('used_count')->unsigned()->default(0);

            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->tinyInteger('is_active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->default(0);
            $table->string('badge', 32)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'date_start', 'date_end'], 'idx_gift_active_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift');
    }
};
