<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed quyền spatie cho màn Setting — xem docs/ROLE-PERMISSION-PLAN.md mục
 * 0.2. Route GET/PUT /rcms/setting vừa được bọc middleware cms.permission
 * (trước đó 'setting' nằm trong Permissions::$_excepts, không gác quyền gì
 * cả). Migration này chỉ tạo 2 permission + gán cho role 'admin' nếu đã có
 * — Phase 1 (registry + `permission:sync`) sẽ thay thế cách làm thủ công
 * này cho MỌI entity, không riêng setting.
 *
 * Dùng raw DB::table (không dùng Eloquent Permission/Role model của spatie)
 * để khỏi phụ thuộc autoload state lúc chạy migration sớm trong pipeline
 * deploy — cùng phong cách với các migration seed setting khác
 * (2026_07_10_000000_reward_hybrid_schema, 2026_07_10_000001_create_affiliate_tables).
 */
return new class () extends Migration {
    private const GUARD = 'web';

    private const PERMISSIONS = ['detail-setting', 'edit-setting'];

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
