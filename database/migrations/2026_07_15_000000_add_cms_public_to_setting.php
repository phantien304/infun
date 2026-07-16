<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cờ `cms_public` vào bảng setting — admin quyết định từng setting có
 * được trả ra endpoint PUBLIC `GET /rcms/system/init` hay không (endpoint này
 * đứng TRƯỚC auth:sanctum, ai cũng gọi được).
 *
 *  - cms_public = 1 (mặc định): SPA thấy được (config_name, config_language...)
 *  - cms_public = 0: chỉ dùng server-side, không bao giờ lộ ra ngoài.
 *
 * Data seed: tắt public cho các key vận hành nguy hiểm/nhạy cảm:
 *  - config_stock_checkout: công tắc kiểm tồn — tắt là on_hand âm dần,
 *    không expose cho CMS để không ai "tiện tay" chỉnh.
 *  - mọi key trông giống credentials (password/secret/token/api_key/private).
 *
 * Thay cho CONFIG_BLOCKLIST hardcode trong SystemController (nay đọc theo cờ).
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('setting') || Schema::hasColumn('setting', 'cms_public')) {
            return;
        }

        Schema::table('setting', function (Blueprint $table) {
            $table->boolean('cms_public')->default(true)->after('serialized')
                ->comment('1 = tra ra GET /rcms/system/init (public); 0 = chi server-side');
        });

        // Tắt public cho key nguy hiểm/nhạy cảm đang có sẵn trong bảng.
        DB::table('setting')->where('key', 'config_stock_checkout')->update(['cms_public' => 0]);
        DB::table('setting')
            ->whereRaw("`key` REGEXP 'password|passwd|secret|token|api_?key|private'")
            ->update(['cms_public' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasTable('setting') && Schema::hasColumn('setting', 'cms_public')) {
            Schema::table('setting', function (Blueprint $table) {
                $table->dropColumn('cms_public');
            });
        }
    }
};
