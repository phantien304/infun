<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng `setting` có PK kép (id, key) với id AUTO_INCREMENT → tồn tại nhiều
 * dòng trùng id (vd id=19 × 3) và `key` không unique. Hậu quả:
 *   - Model Setting (primaryKey mặc định 'id') save()/update() sẽ chạy
 *     WHERE id = ? và sửa đè MỌI dòng trùng id cùng lúc.
 *   - Key trùng thì getConfigs() lặng lẽ lấy dòng sau đè dòng trước.
 *
 * Sau migration:
 *   setting: id INT AUTO_INCREMENT PK (đơn, duy nhất) + UNIQUE(`key`)
 *
 * Dọn data trước khi đổi khóa:
 *   1. Key trùng → giữ dòng id lớn nhất (khớp hành vi hiện tại: dòng sau
 *      đè dòng trước khi build config map).
 *   2. Id trùng → giữ 1 dòng, các dòng còn lại gán id mới từ MAX(id)+1.
 *
 * Idempotent: bỏ qua nếu PK đã là 1 cột. Down chỉ khôi phục cấu trúc khóa,
 * KHÔNG khôi phục được các dòng đã dọn (data cleanup một chiều).
 */
return new class () extends Migration {
    private function primaryKeyColumnCount(): int
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'setting')
            ->where('INDEX_NAME', 'PRIMARY')
            ->count();
    }

    public function up(): void
    {
        if (! Schema::hasTable('setting') || $this->primaryKeyColumnCount() === 1) {
            return;
        }

        // (1) Key trùng: giữ dòng id lớn nhất cho mỗi key.
        DB::statement(
            'DELETE s1 FROM setting s1
                JOIN setting s2 ON s1.`key` = s2.`key` AND s2.id > s1.id'
        );

        // (2) Id trùng: giữ dòng đầu (theo key), gán id mới cho phần còn lại.
        //    Sau bước (1) key đã duy nhất nên đổi id không thể đụng PK kép.
        $duplicatedIds = DB::table('setting')
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('id');

        $nextId = (int) DB::table('setting')->max('id') + 1;
        foreach ($duplicatedIds as $duplicatedId) {
            $keys = DB::table('setting')
                ->where('id', $duplicatedId)
                ->orderBy('key')
                ->pluck('key');

            foreach ($keys->slice(1) as $key) {
                DB::table('setting')
                    ->where('id', $duplicatedId)
                    ->where('key', $key)
                    ->update(['id' => $nextId++]);
            }
        }

        // (3) PK đơn trên id + UNIQUE(key). id vốn AUTO_INCREMENT nên chỉ cần
        //    đổi khóa trong 1 statement (auto-inc luôn phải nằm trong 1 key).
        DB::statement(
            'ALTER TABLE setting
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (id),
                ADD UNIQUE KEY uq_setting_key (`key`)'
        );

        // (4) Đảm bảo counter auto-increment vượt qua các id vừa gán tay.
        $maxId = (int) DB::table('setting')->max('id');
        DB::statement('ALTER TABLE setting AUTO_INCREMENT = ' . ($maxId + 1));
    }

    public function down(): void
    {
        if (! Schema::hasTable('setting') || $this->primaryKeyColumnCount() !== 1) {
            return;
        }

        DB::statement(
            'ALTER TABLE setting
                DROP PRIMARY KEY,
                DROP INDEX uq_setting_key,
                ADD PRIMARY KEY (id, `key`)'
        );
    }
};
