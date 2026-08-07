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

/**
 * Orchestration ghi cho Role — tách khỏi RoleRepository (chỉ đọc) vì chứa 2
 * luật nghiệp vụ bảo vệ hệ thống phân quyền, không phải data access thuần.
 * Đặt ở app/Services/Cms/ (không phải app/Services/ dùng chung storefront)
 * vì: storefront không bao giờ cần orchestration này, và không đụng business
 * rule tính tiền/kho — khớp ngưỡng cho phép của docs/CMS-MODULE-BOUNDARY.md.
 *
 * 3 luật từ docs/ROLE-PERMISSION-PLAN.md mục 0.3 + mục Super Admin (thêm
 * 2026-08-05):
 *  1. Chống LEO THANG: user hiện tại không được gán cho role 1 quyền mà
 *     CHÍNH HỌ không có — nếu không, bất kỳ ai có 'edit-role' là super-admin
 *     trên thực tế (tự cấp cho mình mọi quyền còn lại qua vòng role khác).
 *  2. Chống TỰ KHOÁ: nếu role đang sửa là role CHÍNH user hiện tại đang
 *     thuộc, và sau khi lưu user sẽ MẤT quyền 'edit-role' (không còn qua
 *     role nào khác hay quyền trực tiếp) → chặn, không cho lưu.
 *  3. Bảo vệ role "Super Admin": chỉ chính Super Admin mới được sửa/xoá role
 *     này, và KHÔNG ai (kể cả Super Admin — xoá vĩnh viễn, không phục hồi
 *     được) được xoá nó qua CMS. Không có luật này thì luật (1) vô nghĩa —
 *     ai cũng có thể lách bằng cách tạo role rỗng quyền rồi đặt tên "Super
 *     Admin" (Gate::before ở AppServiceProvider so khớp THEO TÊN, không theo
 *     nội dung quyền).
 *
 * CẢNH BÁO AN TOÀN: destroy() thực hiện XOÁ CỨNG (Spatie\Permission\Models\
 * Role không có deleted_at trong schema — xem migration
 * 2026_06_21_024339_create_permission_tables.php) — KHÔNG hoàn tác được
 * qua "restore". Có guard chặn xoá role còn user gán (RoleInUseException)
 * nhưng KHÔNG miễn trừ việc phải cảnh báo rõ cho người dùng trước khi xoá
 * role không còn ai gán (xem quy tắc an toàn ở docs/CLAUDE.md).
 */
class RoleWriteService
{
    private const GUARD = 'web';

    /**
     * Tên role hệ thống được Gate::before (AppServiceProvider) coi là bypass
     * TOÀN BỘ quyền — xem docblock class + RoleProtectedException. Role này
     * được seed 1 lần qua migration 2026_08_05_010000_seed_super_admin_role
     * (idempotent, KHÔNG gán quyền/user nào — gán Super Admin đầu tiên phải
     * làm thủ công qua tinker, xem docs/ROLE-PERMISSION-PLAN.md).
     */
    public const SUPER_ADMIN_ROLE = 'Super Admin';

    /**
     * @param  array{name: string}  $roleData
     * @param  int[]  $permissionIds  ID trong sp_permissions, lấy từ
     *                                RoleController::permissionsRegistry().
     *
     * @throws RoleProtectedException
     * @throws RoleSelfLockoutException
     */
    public function save(?Role $role, array $roleData, array $permissionIds): Role
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser->hasRole(self::SUPER_ADMIN_ROLE, self::GUARD);

        $targetName = trim((string) ($roleData['name'] ?? ''));
        $touchesProtectedRole = $targetName === self::SUPER_ADMIN_ROLE
            || ($role !== null && $role->name === self::SUPER_ADMIN_ROLE);

        if ($touchesProtectedRole && ! $isSuperAdmin) {
            throw new RoleProtectedException();
        }

        $safePermissionIds = $this->filterToOwnPermissions($currentUser, $permissionIds);

        if ($role !== null) {
            $this->assertNoSelfLockout($currentUser, $role, $safePermissionIds);
        }

        return DB::transaction(function () use ($role, $roleData, $safePermissionIds) {
            $role ??= new Role(['guard_name' => self::GUARD]);
            $role->name = $roleData['name'];
            $role->guard_name = self::GUARD;
            $role->save();

            // syncPermissions() built-in của spatie — KHÔNG tự viết lại
            // delete+insert như mt219 (xem ROLE-PERMISSION-PLAN.md Phase 2.2).
            $role->syncPermissions($safePermissionIds);

            return $role->load('permissions');
        });
    }

    /**
     * @throws RoleInUseException
     * @throws RoleProtectedException
     */
    public function delete(Role $role): void
    {
        if ($role->name === self::SUPER_ADMIN_ROLE) {
            // Chặn TUYỆT ĐỐI, kể cả với chính Super Admin — xoá là hành động
            // xoá cứng không hoàn tác (xem docblock class), và role này là
            // lối thoát hiểm duy nhất của hệ thống. Cần xoá thật thì làm thủ
            // công (migration/tinker), không qua CMS.
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

        // Hard delete — spatie Role KHÔNG hỗ trợ soft delete (xem cảnh báo
        // ở docblock class). Guard trên đã loại trường hợp còn user gán,
        // nhưng thao tác này vẫn không hoàn tác được (mất luôn role_has_permissions
        // pivot rows qua onDelete('cascade')).
        $role->delete();
    }

    /**
     * Chống leo thang qua đường gán ROLE cho USER (khác filterToOwnPermissions()
     * bên dưới vốn chỉ áp dụng khi sửa NỘI DUNG 1 role). Nếu không lọc ở đây,
     * bất kỳ ai có quyền 'edit-user'/'create-user' có thể gán thẳng 1 role có
     * sẵn (kể cả role "Super Admin") cho người khác hoặc chính mình, đi vòng
     * qua toàn bộ 2 guard ở save() — lỗ hổng tồn tại từ Phase 2.3, phát hiện
     * khi thêm cơ chế Super Admin. UserController gọi hàm này TRƯỚC khi đưa
     * role_ids xuống UserRepository::createAdmin()/updateAdmin().
     *
     * Quy tắc: role "Super Admin" chỉ Super Admin mới gán được (cho chính
     * mình hoặc người khác). Role thường: chỉ gán được nếu user hiện tại ĐANG
     * CÓ đủ 100% quyền mà role đó cấp — cùng nguyên tắc "không phát cái mình
     * không có" như filterToOwnPermissions(), áp dụng cho cả kênh gián tiếp
     * (gán role có sẵn) chứ không chỉ kênh trực tiếp (tick quyền cho role).
     *
     * @param  int[]  $roleIds
     *
     * @return int[]
     */
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

    /**
     * Chống leo thang — chỉ giữ lại permission id mà user hiện tại ĐANG CÓ.
     *
     * @param  int[]  $permissionIds
     *
     * @return int[]
     */
    private function filterToOwnPermissions(User $currentUser, array $permissionIds): array
    {
        // array_map('intval', ...) TRƯỚC khi intersect — permission_ids từ
        // JSON request có thể là string ("5") tuỳ client, trong khi
        // getAllPermissions()->pluck('id') luôn là int (Eloquent cast PK).
        // array_intersect so sánh kiểu lỏng nhưng GIỮ NGUYÊN type gốc của
        // phần tử khớp → nếu không ép kiểu trước, $newPermissionIds có thể
        // lẫn string, làm in_array(..., true) (strict) ở assertNoSelfLockout()
        // so sai kiểu và fail-safe nhầm (chặn oan thao tác hợp lệ).
        $permissionIds   = array_map('intval', $permissionIds);
        $myPermissionIds = $currentUser->getAllPermissions()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($permissionIds, $myPermissionIds));
    }

    /**
     * Chống tự khoá — chỉ kiểm tra khi role đang sửa là role user hiện tại
     * đang thuộc. Mô phỏng permission set SAU khi lưu (role khác + quyền
     * trực tiếp của user KHÔNG đổi, chỉ role đang sửa đổi theo $newPermissionIds)
     * rồi kiểm tra 'edit-role' còn hay mất.
     *
     * @param  int[]  $newPermissionIds  Quyền SẮP gán cho $role (đã lọc leo thang).
     *
     * @throws RoleSelfLockoutException
     */
    private function assertNoSelfLockout(User $currentUser, Role $role, array $newPermissionIds): void
    {
        if (! $currentUser->hasRole($role)) {
            return; // Role đang sửa không liên quan tới user hiện tại — không có gì để tự khoá.
        }

        $editRolePermissionId = Permission::query()
            ->where('name', 'edit-role')
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($editRolePermissionId === null) {
            return; // Quyền 'edit-role' chưa tồn tại trong hệ thống — chạy permission:sync trước.
        }

        // in_array LỎNG (không strict) — ->value('id') có thể trả string tuỳ
        // driver PDO, $newPermissionIds đã ép int ở filterToOwnPermissions()
        // nhưng không dựa vào đó, so lỏng cho chắc thay vì strict rồi fail-safe
        // nhầm (chặn oan) hoặc bug ngược (không chặn khi lẽ ra phải chặn).
        if (in_array($editRolePermissionId, $newPermissionIds)) {
            return; // Vẫn còn edit-role NGAY TRONG role đang sửa — an toàn.
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
