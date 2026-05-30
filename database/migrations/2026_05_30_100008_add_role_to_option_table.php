<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * option.role: tách "domain role" khỏi "input widget type".
 *
 * Trước: cột `type` gánh 2 trách nhiệm — render widget (radio/select/date...)
 * VÀ phân loại "tham gia variant hay là custom field". Khắp codebase phải
 * lặp `in_array($type, ['radio','checkbox','select','image'])` để hỏi câu
 * thứ 2 — scatter logic, dễ drift.
 *
 * Sau: `role TINYINT UNSIGNED` là nguồn sự thật duy nhất cho domain role.
 * `type` chỉ còn lo widget render.
 *
 * Quy ước giá trị (dùng numeric flag để dễ mở rộng tương lai):
 *   0 = custom_field   — user điền lúc checkout (text, date, file...)
 *   1 = variant        — tạo SKU (radio, checkbox, select, image)
 *   2..9 = reserved    — slot cho role tương lai (bundle_part, addon, gift...)
 *
 * Model bắt buộc khai báo hằng số tương ứng:
 *   class Option {
 *       public const ROLE_CUSTOM_FIELD = 0;
 *       public const ROLE_VARIANT      = 1;
 *   }
 * Code dùng Option::ROLE_VARIANT thay vì literal 1, tránh magic number.
 *
 * Backfill rule:
 *   - role = 1 (variant)       khi type ∈ (radio, checkbox, select, image)
 *   - role = 0 (custom_field)  cho mọi type còn lại
 *
 * Default 0 (custom_field) để row mới chưa pick role không bị coi nhầm là
 * variant — variant tạo SKU, sai một cái chi phí cao.
 *
 * CHECK constraint giới hạn role ∈ (0, 1) — khi muốn thêm role mới, phải
 * ALTER CHECK 1 lần. Đây là intentional: DB schema document chính nó về
 * tập giá trị hợp lệ, không tin app silent insert role lạ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table) {
            $table->unsignedTinyInteger('role')
                ->default(0)
                ->after('type')
                ->comment('0=custom_field, 1=variant; 2..9 reserved future');
        });

        // Backfill từ type hiện có. 1 lệnh SQL, không kéo từng row qua PHP.
        DB::statement("
            UPDATE `option`
            SET role = CASE
                WHEN type IN ('radio', 'checkbox', 'select', 'image') THEN 1
                ELSE 0
            END
        ");

        // CHECK constraint cưỡng chế tập giá trị ở DB layer. MySQL 8.0.16+
        // và MariaDB 10.2+ enforce CHECK; trước đó parse-only (vẫn an toàn
        // — không gây lỗi runtime).
        DB::statement("
            ALTER TABLE `option`
            ADD CONSTRAINT chk_option_role CHECK (role IN (0, 1))
        ");

        // Index cho filter active variant options (sidebar, query DTO build).
        Schema::table('option', function (Blueprint $table) {
            $table->index(['role', 'deleted_at'], 'idx_option_role_active');
        });
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table) {
            $table->dropIndex('idx_option_role_active');
        });

        // Drop CHECK trước drop column (một số phiên bản MySQL bắt buộc).
        DB::statement('ALTER TABLE `option` DROP CONSTRAINT chk_option_role');

        Schema::table('option', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
