<?php

namespace App\Models\Entities;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Subclass local của Spatie\Permission\Models\Permission — cùng lý do/ngày
 * với App\Models\Entities\Role (xem docblock class đó để biết đầy đủ bối
 * cảnh). Bảng `sp_permissions` chỉ có 2 cột thật sự ghi được ngoài
 * id/timestamps: name và guard_name.
 *
 * config/permission.php 'models.permission' đã trỏ về class này. Chỗ TỰ
 * IMPORT `Spatie\Permission\Models\Permission` trực tiếp (RoleWriteService,
 * RoleController, PermissionSyncCommand) đã đổi sang import
 * App\Models\Entities\Permission — quan trọng nhất là
 * PermissionSyncCommand::runSync() gọi `Permission::findOrCreate($code,
 * self::GUARD)`, hàm static này dùng `static::query()->create(...)` nội bộ
 * (spatie HasPermissions trait) nên PHẢI gọi qua class này (không phải vendor
 * class) thì $fillable mới thật sự áp dụng khi tạo permission mới.
 */
class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
    ];
}
