<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\UserData;
use App\Http\Requests\Cms\UserRequest;
use App\Models\Entities\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Cms\RoleWriteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\PaginatedDataCollection;

class UserController extends BaseCmsController
{
    protected string $permission = 'user';

    public function __construct(
        private readonly UserRepositoryInterface $repo,
        private readonly RoleWriteService $roleWriteService,
    ) {
    }

    public function index(Request $request)
    {
        $type = $request->filled('type') ? (int) $request->input('type') : null;
        $users = $this->repo->searchForCms(
            (string) $request->input('keyword', ''),
            $type,
            (int) $request->input('per_page', 10)
        );

        return respondSuccess(
            $users->map(fn ($u) => [
                'id'        => $u->id,
                'full_name' => $u->full_name,
                'email'     => $u->email,
                'telephone' => $u->userPhone?->phone ?? '',
            ])->values(),
        );
    }

    public function adminList(Request $request)
    {
        return UserData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(UserRequest $request)
    {
        $roleIds = $this->roleWriteService->filterAssignableRoleIds(Auth::user(), $request->input('role_ids', []));
        $user = $this->repo->createAdmin($request->validated(), $roleIds);

        return respondCreated(UserData::fromModel($user), 'user_created');
    }

    public function show(User $user)
    {
        abort_if((int) $user->type !== 1, 404);
        $user->load('roles');

        return respondSuccess(UserData::fromModel($user));
    }

    public function update(UserRequest $request, User $user)
    {
        abort_if((int) $user->type !== 1, 404);
        $roleIds = $this->roleWriteService->filterAssignableRoleIds(Auth::user(), $request->input('role_ids', []));
        $user = $this->repo->updateAdmin($user, $request->validated(), $roleIds);

        return respondSuccess(UserData::fromModel($user), 'user_updated');
    }

    public function destroy(User $user)
    {
        abort_if((int) $user->type !== 1, 404);

        if ((int) $user->id === (int) Auth::id()) {
            return respondUnprocessable('Không thể tự xoá tài khoản đang đăng nhập.');
        }

        $this->repo->deleteByIds([$user->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $user = $this->repo->restoreById((int) $id);
        abort_if($user === null, 404);

        return respondSuccess(UserData::fromModel($user), 'user_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $ids = $data['ids'];
        if ($data['action'] === 'delete') {
            $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) Auth::id()));
            $affected = $this->repo->deleteByIds($ids);
        } else {
            $affected = $this->repo->restoreByIds($ids);
        }

        return respondSuccess(['affected' => $affected]);
    }
}
