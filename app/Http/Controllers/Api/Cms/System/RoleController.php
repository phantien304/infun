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
                        'id' => $idByName->get($code),
                    ];
                })->all(),
            ],
        )->all();

        return respondSuccess($entities);
    }

    public function store(RoleRequest $request)
    {
        try {
            $role = $this->writeService->save(null, $request->validated(), $request->input('permission_ids', []));
        } catch (RoleProtectedException|RoleSelfLockoutException $e) {
            return respondUnprocessable($e->getMessage());
        }

        return respondCreated(RoleData::fromModel($role), 'role_created');
    }

    public function show(Role $role)
    {
        $role->load('permissions');

        return respondSuccess(RoleData::fromModel($role), 'role_shown');
    }

    public function update(RoleRequest $request, Role $role)
    {
        try {
            $role = $this->writeService->save($role, $request->validated(), $request->input('permission_ids', []));
        } catch (RoleProtectedException|RoleSelfLockoutException $e) {
            return respondUnprocessable($e->getMessage());
        }

        return respondSuccess(RoleData::fromModel($role), 'role_updated');
    }

    public function destroy(Role $role)
    {
        try {
            $this->writeService->delete($role);
        } catch (RoleInUseException|RoleProtectedException $e) {
            return respondUnprocessable($e->getMessage());
        }

        return response()->noContent();
    }
}
