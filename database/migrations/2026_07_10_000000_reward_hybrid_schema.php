<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reward hybrid (2026-07-10) — chuyển hệ điểm thưởng từ mô hình OpenCart
 * (points-price per product) sang hybrid: earn global-rate + override per-product,
 * redeem điểm = tiền có cap, điểm pending tới khi giao thành công, hết hạn optional.
 *
 * 1. `user_reward.expires_at` — NULL = không hết hạn; set = mốc hết hạn.
 *    Gán khi điểm được kích hoạt (giao thành công), theo config_reward_expiry_months.
 * 2. `user_reward.status` — trước giờ NULL (chưa dùng). Semantics mới:
 *    0=pending (chờ giao), 1=available (tính vào balance), 2=revoked (đơn hủy).
 *    Backfill row cũ NULL → 1 (coi như đã khả dụng, giữ balance cũ).
 * 3. Drop `orders.reward` varchar(128) — legacy OpenCart, không code nào ghi/đọc;
 *    flow mới tra điểm đã tiêu qua `user_reward` (row âm) + `orders_total` (code=reward).
 * 4. Seed setting cho admin chỉnh trong CMS (bảng `setting`, code=config):
 *    - config_reward_point_enabled     : 1 bật / 0 tắt toàn hệ điểm
 *    - config_reward_earn_divisor      : X đồng = 1 điểm (fallback khi product
 *                                        không có row product_reward); 0 = tắt fallback
 *    - config_reward_redeem_rate       : 1 điểm = X đồng khi tiêu
 *    - config_reward_redeem_max_percent: cap % giá trị đơn được trả bằng điểm
 *    - config_reward_expiry_months     : điểm sống X tháng sau kích hoạt; 0 = vĩnh viễn
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('user_reward', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('points');
            $table->index(['user_id', 'status'], 'idx_user_reward_user_status');
            $table->index(['order_id', 'transaction_type'], 'idx_user_reward_order_type');
        });

        // Row cũ status NULL → available, giữ nguyên balance hiện có.
        DB::table('user_reward')->whereNull('status')->update(['status' => 1]);

        if (Schema::hasColumn('orders', 'reward')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('reward');
            });
        }

        $settings = [
            'config_reward_point_enabled'      => '1',
            'config_reward_earn_divisor'       => '100',
            'config_reward_redeem_rate'        => '1',
            'config_reward_redeem_max_percent' => '50',
            'config_reward_expiry_months'      => '6',
        ];
        foreach ($settings as $key => $value) {
            DB::table('setting')->insertOrIgnore([
                'code'       => 'config',
                'key'        => $key,
                'value'      => $value,
                'serialized' => 0,
                'system'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget(getCoreConfig('cache.setting'));
        $this->flushSchemaCache();
    }

    /**
     * HasSchemaCache cache()->forever danh sách cột (dùng cho getFillable
     * fallback) — thêm/xóa cột xong phải flush, không thì mass assignment
     * không thấy expires_at và orders vẫn tưởng còn cột reward.
     */
    protected function flushSchemaCache(): void
    {
        $conn = DB::connection();
        foreach (['user_reward', 'orders'] as $table) {
            Cache::forget(implode(':', [
                'schema',
                $conn->getDatabaseName(),
                $conn->getDriverName(),
                $table,
            ]));
        }
    }

    public function down(): void
    {
        Schema::table('user_reward', function (Blueprint $table) {
            $table->dropIndex('idx_user_reward_user_status');
            $table->dropIndex('idx_user_reward_order_type');
            $table->dropColumn('expires_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('reward', 128)->nullable();
        });

        DB::table('setting')->whereIn('key', [
            'config_reward_point_enabled',
            'config_reward_earn_divisor',
            'config_reward_redeem_rate',
            'config_reward_redeem_max_percent',
            'config_reward_expiry_months',
        ])->delete();

        Cache::forget(getCoreConfig('cache.setting'));
        $this->flushSchemaCache();
    }
};
