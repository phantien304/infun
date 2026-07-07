<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm surrogate PK `id` cho weight_class_description và
 * length_class_description (trước đây PK kép class_id+language_code —
 * Eloquent không hỗ trợ composite PK chuẩn, save()/delete() phải dựa vào
 * trait HasCompositeKey). Khóa tự nhiên giữ lại dưới dạng UNIQUE để chống
 * trùng bản dịch. Cùng hướng với add_surrogate_id_to_product_option.
 *
 * Idempotent: kiểm tra hasColumn trước mỗi bước.
 */
return new class () extends Migration {
    /** [table => cột khóa ngoại về bảng cha] */
    private array $tables = [
        'weight_class_description' => 'weight_class_id',
        'length_class_description' => 'length_class_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $classIdColumn) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::statement(
                "ALTER TABLE {$table}
                    DROP PRIMARY KEY,
                    ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
                    ADD UNIQUE KEY uq_{$table} ({$classIdColumn}, language_code)"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $classIdColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::statement(
                "ALTER TABLE {$table}
                    DROP PRIMARY KEY,
                    DROP INDEX uq_{$table},
                    DROP COLUMN id,
                    ADD PRIMARY KEY ({$classIdColumn}, language_code)"
            );
        }
    }
};
