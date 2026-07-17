<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quy tắc tặng voucher theo giá trị đơn — "đơn từ 1tr tặng voucher 50k".
 *
 * Mỗi chương trình = 1 row. Đơn đạt `min_order_total` và chuyển sang trạng thái
 * hoàn tất (getConfigDb('order_complete_status_all')) → sinh 1 voucher (bảng
 * `voucher` có sẵn) cho khách, dùng cho các đơn SAU.
 *
 * - `min_order_total` so với `orders.total` (tổng cuối sau giảm giá, gồm ship).
 *   Muốn đổi cơ sở tính (vd sub_total trước ship) thì sửa MỘT chỗ trong
 *   VoucherRewardService, không đổi schema.
 * - `reward_expire_days`: HSD của voucher được tặng, tính từ lúc phát.
 * - `max_per_user` NULL = không giới hạn; quota ngân sách = `quota_total`
 *   so với `granted_count` denormalize (conditional UPDATE — pattern
 *   gift.used_count, chống đua khi 2 đơn hoàn tất cùng lúc).
 * - `status` TINYINT: 1=active, 2=paused — enum VoucherRewardRuleStatus.
 *   Trumps date window: admin pause giữa chừng bất kể date_end.
 *
 * KHÔNG hardcode literal — code đọc qua enum, không magic number.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voucher_reward_rule')) {
            return;
        }

        Schema::create('voucher_reward_rule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 128);
            $table->text('description')->nullable();

            $table->decimal('min_order_total', 15, 2);
            $table->decimal('reward_amount', 15, 2);
            $table->integer('reward_expire_days')->unsigned();

            $table->integer('max_per_user')->unsigned()->nullable();
            $table->integer('quota_total')->unsigned()->nullable();
            $table->integer('granted_count')->unsigned()->default(0);

            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->tinyInteger('status')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'date_start', 'date_end'], 'idx_vrr_active_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_reward_rule');
    }
};
