<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed quyền spatie cho màn Warehouse (CRUD kho, tính năng đa kho) — cùng
 * cách làm với 2026_08_03_000003_seed_setting_cms_permissions (raw DB::table,
 * không dùng Eloquent Permission/Role, tránh phụ thuộc autoload lúc migrate
 * sớm trong pipeline deploy). Route Route::cmsApiResource('warehouse', ...)
 * tự map action → quyền spatie qua CmsPermission::MAP ({list,detail,create,
 * edit,del}-warehouse) — nếu quyền không tồn tại thì Gate::authorize() luôn
 * 403 cho user không phải super-admin.
 */
return new class () extends Migration {
    private const GUARD = 'web';

    private const PERMISSIONS = ['list-warehouse', 'detail-warehouse', 'create-warehouse', 'edit-warehouse', 'del-warehouse'];

    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'sp_permissions';
        $rolesTable = $tableNames['roles'] ?? 'sp_roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'sp_role_has_permissions';

        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table($permissionsTable)->insertOrIgnore([
                'name'       => $name,
                'guard_name' => self::GUARD,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', self::GUARD)
            ->pluck('id');

        // Gán cho role 'admin' NẾU đã tồn tại (SpatiePermissionSeeder tạo role
        // này) — môi trường chưa từng seed thì bỏ qua bước này, permission vẫn
        // tồn tại sẵn để gán tay/qua seeder sau.
        $adminRoleId = DB::table($rolesTable)
            ->where('name', 'admin')
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($adminRoleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table($roleHasPermissionsTable)->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id'       => $adminRoleId,
                ]);
            }
        }

        $this->flushPermissionCache();
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'sp_permissions';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'sp_role_has_permissions';

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', self::GUARD)
            ->pluck('id');

        DB::table($roleHasPermissionsTable)->whereIn('permission_id', $permissionIds)->delete();
        DB::table($permissionsTable)->whereIn('name', self::PERMISSIONS)->where('guard_name', self::GUARD)->delete();

        $this->flushPermissionCache();
    }

    private function flushPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
