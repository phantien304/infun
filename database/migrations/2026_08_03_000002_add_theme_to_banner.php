<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép gán 1 banner cho 1 theme cụ thể — mirror
 * `2026_08_02_000001_add_theme_to_menu.php` (xem config/theme.php — hệ theme
 * multi-tenant: nhiều hostname → nhiều theme, cùng 1 codebase). `theme = NULL`
 * nghĩa là banner "mặc định/mọi theme" — hành vi CŨ (mọi banner theo page/
 * position đều hiển thị bất kể theme nào đang active) được BẢO TOÀN qua case
 * NULL này, không phá site hiện tại khi deploy migration.
 *
 * `theme` KHÔNG có FK — validate ở tầng app (BannerRequest) đối chiếu
 * `config('theme.available')`, giống MenuRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('banner', 'theme')) {
            return;
        }
        Schema::table('banner', function (Blueprint $table) {
            $table->string('theme', 100)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('banner', 'theme')) {
            return;
        }
        Schema::table('banner', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
