<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển menu_value từ xoá cứng sang soft-delete — an toàn hơn khi CMS
 * (MenuValueController::destroy / deleteCategories) xoá node cây menu.
 *
 * Xoá bảng menu_value_description (translation title/link) VẪN xoá cứng
 * như cũ ($destroyRelations trên model MenuValue) — chỉ hàng menu_value
 * chính được giữ lại (deleted_at) để không mất dấu vết. KHÔNG dựng UI
 * khôi phục ở đợt này (theo yêu cầu) — deleted_at chỉ đổi mức an toàn xoá,
 * ai cần khôi phục thì sửa thẳng DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_value', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('menu_value', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
