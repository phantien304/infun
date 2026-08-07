<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép gán 1 menu cho 1 theme cụ thể (xem config/theme.php — hệ theme
 * multi-tenant: nhiều hostname → nhiều theme, cùng 1 codebase). `theme = NULL`
 * nghĩa là menu "mặc định/mọi theme" — hành vi CŨ (mọi menu vị trí 'top' đều
 * hiển thị bất kể theme nào đang active) được BẢO TOÀN qua case NULL này,
 * không phá site hiện tại khi deploy migration.
 *
 * `theme` KHÔNG có FK — validate ở tầng app (MenuRequest) đối chiếu
 * `config('theme.available')`, giống cách ThemeManager::sanitize() đã làm,
 * vì đây là allowlist cấu hình (không phải bảng DB) — xem
 * App\Helpers\ThemeManager::sanitize().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->string('theme', 100)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
