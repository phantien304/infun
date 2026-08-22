<?php

namespace App\Services\Cms;

use App\Exceptions\RoleInUseException;
use App\Exceptions\RoleProtectedException;
use App\Exceptions\RoleSelfLockoutException;
use App\Models\Entities\Permission;
use App\Models\Entities\Role;
use App\Models\Entities\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoleWriteService
{
    private const GUARD = 'web';

    public const SUPER_ADMIN_ROLE = 'Super Admin';

    public function save(?Role $role, array $roleData, array $permissionIds): Role
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser->hasRole(self::SUPER_ADMIN_ROLE, self::GUARD);

        $targetName = trim((string) ($roleData['name'] ?? ''));
        $touchesProtectedRole = $targetName === self::SUPER_ADMIN_ROLE
            || ($role !== null && $role->name === self::SUPER_ADMIN_ROLE);

        if ($touchesProtectedRole && ! $isSuperAdmin) {
            throw new RoleProtectedException();
        }

        /**
         * Super Admin KHÔNG filter theo getAllPermissions() — bypass của Super
         * Admin nằm ở Gate::before() (AppServiceProvider::registerSuperAdminBypass),
         * KHÔNG gán permission thật vào pivot, nên getAllPermissions() của Super
         * Admin luôn rỗng. Nếu áp filterToOwnPermissions() cho cả Super Admin,
         * array_intersect() với danh sách rỗng luôn ra [], khiến Super Admin
         * không thể gán BẤT KỲ permission nào cho role khác (bug đã phát hiện:
         * "không thể thêm permission cho role admin").
         */
        $safePermissionIds = $isSuperAdmin
            ? array_values(array_unique(array_map('intval', $permissionIds)))
            : $this->filterToOwnPermissions($currentUser, $permissionIds);

        if ($role !== null) {
            $this->assertNoSelfLockout($currentUser, $role, $safePermissionIds);
        }

        return DB::transaction(function () use ($role, $roleData, $safePermissionIds) {
            $role ??= new Role(['guard_name' => self::GUARD]);
            $role->name = $roleData['name'];
            $role->guard_name = self::GUARD;
            $role->save();

            $role->syncPermissions($safePermissionIds);

            return $role->load('permissions');
        });
    }

    public function delete(Role $role): void
    {
        if ($role->name === self::SUPER_ADMIN_ROLE) {
            throw new RoleProtectedException(
                'Không thể xoá role "Super Admin" qua CMS — đây là role hệ thống (lối thoát hiểm duy nhất). '
                . 'Xoá qua CMS là xoá vĩnh viễn, không phục hồi được.',
            );
        }

        if ($role->users()->exists()) {
            throw new RoleInUseException(
                "Không thể xoá role \"{$role->name}\": vẫn còn tài khoản đang được gán role này. "
                . 'Gỡ hết user khỏi role trước khi xoá.',
            );
        }
        $role->delete();
    }

    public function filterAssignableRoleIds(User $currentUser, array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return [];
        }

        $isSuperAdmin = $currentUser->hasRole(self::SUPER_ADMIN_ROLE, self::GUARD);

        $candidates = Role::query()
            ->whereIn('id', $roleIds)
            ->where('guard_name', self::GUARD)
            ->with('permissions')
            ->get();

        if ($isSuperAdmin) {
            return $candidates->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        $myPermissionNames = $currentUser->getAllPermissions()->pluck('name')->all();

        return $candidates
            ->reject(fn (Role $role) => $role->name === self::SUPER_ADMIN_ROLE)
            ->filter(
                fn (Role $role) => $role->permissions->pluck('name')
                    ->every(fn (string $name) => in_array($name, $myPermissionNames, true)),
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function filterToOwnPermissions(User $currentUser, array $permissionIds): array
    {
        $permissionIds   = array_map('intval', $permissionIds);
        $myPermissionIds = $currentUser->getAllPermissions()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($permissionIds, $myPermissionIds));
    }

    private function assertNoSelfLockout(User $currentUser, Role $role, array $newPermissionIds): void
    {
        if (! $currentUser->hasRole($role)) {
            return;
        }

        $editRolePermissionId = Permission::query()
            ->where('name', 'edit-role')
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($editRolePermissionId === null) {
            return;
        }

        if (in_array($editRolePermissionId, $newPermissionIds)) {
            return;
        }

        $hasEditRoleViaOtherRoles = $currentUser->roles
            ->reject(fn (Role $r) => $r->is($role))
            ->contains(fn (Role $r) => $r->hasPermissionTo('edit-role', self::GUARD));

        $hasEditRoleDirect = $currentUser->getDirectPermissions()
            ->contains(fn (Permission $p) => $p->name === 'edit-role');

        if (! $hasEditRoleViaOtherRoles && ! $hasEditRoleDirect) {
            throw new RoleSelfLockoutException();
        }
    }
}
