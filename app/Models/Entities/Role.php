<?php

namespace App\Models\Entities;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Subclass local của Spatie\Permission\Models\Role — thêm 2026-08-06 theo
 * yêu cầu user ("thêm fillable vào các entity của tính năng role, permission
 * này"). Model gốc của spatie dùng `protected $guarded = [];` (mở mass-
 * assignment TOÀN BỘ cột trừ khoá chính) — không dùng $fillable. Bảng
 * `sp_roles` (migration 2026_06_21_024339_create_permission_tables.php, xem
 * chi tiết cột) chỉ có 2 cột thật sự ghi được ngoài id/timestamps: name và
 * guard_name (teams đang tắt — config/permission.php 'teams' => false, nên
 * không có team_foreign_key). Class này CHỈ siết lại $fillable, không đổi
 * hành vi/quan hệ/scope nào khác của model gốc.
 *
 * config/permission.php 'models.role' đã trỏ về class này thay vì vendor
 * class trực tiếp — spatie/laravel-permission tự resolve Role qua config này
 * ở mọi nơi dùng trait HasRoles/HasPermissions (vd $user->roles(),
 * $user->hasRole()), nên KHÔNG cần sửa gì thêm cho các chỗ đó. Nhưng những
 * chỗ TỰ IMPORT `Spatie\Permission\Models\Role` trực tiếp (RoleController,
 * RoleWriteService, RoleRepository, RoleData, RoleRepositoryInterface) đã
 * được đổi sang import App\Models\Entities\Role — nếu thêm code mới cần
 * dùng Role, PHẢI import từ đây, KHÔNG import thẳng vendor class, nếu không
 * sẽ bỏ lỡ $fillable vừa khai báo.
 *
 * LƯU Ý namespace: App\Models\Entities\Role TỪNG là model Role tự viết
 * (Phase 0 cũ) — ĐÃ XOÁ ở Phase 4 khi migrate hẳn sang spatie/laravel-
 * permission (xem docs/ROLE-PERMISSION-PLAN.md Phase 4, migration
 * 2026_08_05_000000_drop_legacy_role_permission_tables.php). Class này tái sử
 * dụng cùng tên/namespace cho mục đích khác hẳn (subclass spatie), không
 * phải hồi sinh code cũ.
 */
class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
    ];
}
