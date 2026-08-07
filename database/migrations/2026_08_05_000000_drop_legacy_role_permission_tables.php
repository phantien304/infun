<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 dọn rác (docs/ROLE-PERMISSION-PLAN.md) — xoá CỨNG 5 bảng legacy
 * đã bị spatie/laravel-permission (sp_*) thay thế hoàn toàn từ Phase 0-3:
 *   permissions, roles, role_user, permission_user, permission_role.
 *
 * ⚠️ KHÔNG THỂ HOÀN TÁC — xem docs/CLAUDE.md mục "QUY TẮC AN TOÀN". down()
 * dưới đây KHÔNG khôi phục được dữ liệu (5 bảng này chưa từng có migration
 * CREATE trong repo — import 1 lần từ DB legacy mt219, không có schema nào
 * để dựng lại đúng nguyên bản). Muốn hoàn tác THẬT phải restore từ backup
 * DB trước thời điểm chạy migration này.
 *
 * Đã audit TRƯỚC khi viết file này (2026-08-05, grep toàn repo infun, loại
 * trừ vendor/node_modules):
 *  - Model App\Models\Entities\{Role,Permission,RoleUser,PermissionRole} —
 *    ĐÃ XOÁ (0 caller còn lại ngoài chính chúng).
 *  - User::legacyRoles()/legacyPermissions() (đổi tên khỏi roles()/
 *    permissions() ở Phase 0 vì che mất trait HasRoles/HasPermissions của
 *    spatie) — ĐÃ XOÁ, 0 caller.
 *  - database/seeders/SpatiePermissionSeeder.php (chỗ DUY NHẤT còn
 *    `DB::table('permissions')`) — ĐÃ XOÁ. Không entrypoint/CI nào tự chạy
 *    seeder này (grep docker/ không thấy `db:seed`/`SpatiePermissionSeeder`).
 *  - 2 migration seed quyền CMS gần đây (warehouse/setting) chỉ đụng bảng
 *    sp_* (spatie), KHÔNG đọc/ghi 5 bảng legacy này.
 *
 * Lưu ý: plan gốc (docs/ROLE-PERMISSION-PLAN.md, Phase 4) khuyến nghị đợi
 * Phase 0-3 chạy ổn định ≥1 tuần rồi mới xoá — dọn dẹp này chạy ở ngày
 * Phase 3 xong (2026-08-05), theo yêu cầu tường minh của user ("xóa luôn"),
 * KHÔNG đợi đủ 1 tuần như khuyến nghị ban đầu.
 */
return new class () extends Migration {
    private const TABLES = [
        'role_user',        // pivot User<->Role legacy (morph 'user', cột model_type sai shape spatie)
        'permission_user',  // pivot User<->Permission legacy
        'permission_role',  // pivot Role<->Permission legacy
        'roles',            // bảng role legacy (KHÔNG phải sp_roles của spatie)
        'permissions',      // bảng permission legacy — nguồn import 1 lần cho SpatiePermissionSeeder (đã xoá)
    ];

    public function up(): void
    {
        // Xoá pivot trước (role_user/permission_user/permission_role), rồi
        // mới xoá 2 bảng cha (roles/permissions) — thứ tự không bắt buộc vì
        // không có FK constraint nào được khai (import raw từ mt219), nhưng
        // giữ thứ tự này cho rõ ý đồ nếu môi trường nào đó có FK ẩn.
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // KHÔNG khôi phục được — xem docblock đầu file. Để trống có chủ đích
        // thay vì giả vờ có rollback: 5 bảng này chưa từng có migration
        // CREATE gốc trong repo nên không có schema nào để dựng lại đúng, và
        // dữ liệu (nếu có) đã mất thật sau khi up() chạy. Cần dữ liệu lại =
        // restore từ DB backup trước thời điểm migrate này.
    }
};
