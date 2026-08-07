<?php

namespace App\Http\Controllers\Api\Cms\System;

use App\Data\Cms\RoleData;
use App\Enums\CmsPermissionEntity;
use App\Exceptions\RoleInUseException;
use App\Exceptions\RoleProtectedException;
use App\Exceptions\RoleSelfLockoutException;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\RoleRequest;
use App\Models\Entities\Permission;
use App\Models\Entities\Role;
use App\Repositories\Interfaces\RoleRepositoryInterface;
use App\Services\Cms\RoleWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Role API (REST) cho CMS — Phase 2.2 (docs/ROLE-PERMISSION-PLAN.md).
 * Không dùng cmsApiResource (Spatie\Permission\Models\Role KHÔNG có
 * deleted_at — không hỗ trợ withTrashed/restore, xem RoleWriteService).
 * Ghi (store/update/destroy) đi qua RoleWriteService — chứa 2 luật chống
 * leo thang/tự khoá, KHÔNG đặt trong controller.
 */
class RoleController extends BaseCmsController
{
    protected string $permission = 'role';

    public function __construct(
        private readonly RoleRepositoryInterface $repo,
        private readonly RoleWriteService $writeService,
    ) {
    }

    public function index(Request $request)
    {
        return RoleData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    /**
     * GET /rcms/role/permissions/registry — toàn bộ mã quyền CMS (nguồn:
     * App\Enums\CmsPermissionEntity — registry TĨNH của Phase 1) kèm id thật
     * trong sp_permissions. Form Role (tạo mới lẫn sửa) dùng payload này để
     * dựng ma trận checkbox — thay thế _getRolePermission() quét route lúc
     * runtime của mt219. Tách route riêng (không gộp vào show) vì form "Tạo
     * role mới" cần danh sách này TRƯỚC KHI có role id.
     */
    public function permissionsRegistry(): JsonResponse
    {
        $idByName = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', CmsPermissionEntity::allPermissionCodes())
            ->pluck('id', 'name');

        $entities = collect(CmsPermissionEntity::cases())->map(
            fn (CmsPermissionEntity $entity) => [
                'entity'      => $entity->value,
                'permissions' => collect(CmsPermissionEntity::ACTIONS)->map(function (string $action) use ($entity, $idByName) {
                    $code = $action . '-' . $entity->value;

                    return [
                        'action' => $action,
                        'code'   => $code,
                        // null nếu chưa chạy `php artisan permission:sync` —
                        // FE nên disable checkbox này thay vì gửi id rỗng.
                        'id' => $idByName->get($code),
                    ];
                })->all(),
            ],
        )->all();

        return response()->json(['data' => $entities]);
    }

    public function store(RoleRequest $request)
    {
        try {
            $role = $this->writeService->save(null, $request->validated(), $request->input('permission_ids', []));
        } catch (RoleProtectedException|RoleSelfLockoutException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => RoleData::fromModel($role)], 201);
    }

    public function show(Role $role)
    {
        $role->load('permissions');

        return response()->json(['data' => RoleData::fromModel($role)]);
    }

    public function update(RoleRequest $request, Role $role)
    {
        try {
            $role = $this->writeService->save($role, $request->validated(), $request->input('permission_ids', []));
        } catch (RoleProtectedException|RoleSelfLockoutException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => RoleData::fromModel($role)]);
    }

    /**
     * XOÁ CỨNG — sp_roles không có deleted_at, không có "restore" cho Role.
     * RoleWriteService::delete() chặn nếu còn user gán (RoleInUseException)
     * hoặc nếu là role hệ thống "Super Admin" (RoleProtectedException, chặn
     * TUYỆT ĐỐI kể cả với chính Super Admin — xem docblock service).
     */
    public function destroy(Role $role)
    {
        try {
            $this->writeService->delete($role);
        } catch (RoleInUseException|RoleProtectedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->noContent();
    }
}
