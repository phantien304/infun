<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển kho mặc định từ core config (stock.default_warehouse_id — sửa phải
 * deploy) sang bảng setting `config_warehouse_id` — admin đổi được trong CMS,
 * cùng hàng với config_weight_class_id / config_length_class_id.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('setting')->insertOrIgnore([
            'code'       => 'config',
            'key'        => 'config_warehouse_id',
            'value'      => '1',
            'serialized' => 0,
            'system'     => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Flush cache setting để getConfigDb thấy key mới ngay.
        Cache::forget(getCoreConfig('cache.setting'));
    }

    public function down(): void
    {
        DB::table('setting')->where('key', 'config_warehouse_id')->delete();
        Cache::forget(getCoreConfig('cache.setting'));
    }
};
