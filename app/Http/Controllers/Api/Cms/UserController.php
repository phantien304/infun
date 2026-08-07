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

/**
 * User API cho CMS — permission slug 'user'.
 *
 * index() là route CŨ (GET /rcms/user, dropdown Author cho blog/form.jsx —
 * xem routes/rcms.php) — GIỮ NGUYÊN signature/response shape, KHÔNG đụng.
 * Phase 2.3 (docs/ROLE-PERMISSION-PLAN.md) thêm CRUD tài khoản quản trị
 * (type=1 — quyết định Phase 6.1 "chỉ Admin") bên dưới:
 *  - adminList(): route riêng `GET /rcms/user/admin-list` (KHÔNG dùng lại
 *    `index` — 2 route cùng path `GET /rcms/user` sẽ đụng nhau, xem comment
 *    ở routes/rcms.php chỗ đăng ký).
 *  - store/show/update/destroy/restore/bulk: đăng ký qua
 *    Route::apiResource('user', ...)->except(['index'])->withTrashed() —
 *    loại 'index' ra khỏi resource để không đè route dropdown cũ.
 *
 * Class KHÔNG chuyển vào namespace Api\Cms\System (dù về mặt domain đúng ra
 * thuộc System) — controller đã tồn tại + đang được route/permission
 * reference, đổi namespace không cần thiết cho Phase 2.3, để dành nếu có
 * đợt dọn dẹp namespace riêng.
 */
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

        return response()->json([
            'data' => $users->map(fn ($u) => [
                'id'        => $u->id,
                'full_name' => $u->full_name,
                'email'     => $u->email,
                'telephone' => $u->userPhone?->phone ?? '',
            ])->values(),
        ]);
    }

    /** GET /rcms/user/admin-list — danh sách CMS đầy đủ (paginate, DTO, chỉ type=1). */
    public function adminList(Request $request)
    {
        return UserData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(UserRequest $request)
    {
        // filterAssignableRoleIds() (thêm 2026-08-05, docs/ROLE-PERMISSION-PLAN.md
        // mục Super Admin) — chống leo thang qua đường gán THẲNG 1 role có
        // sẵn (kể cả "Super Admin") cho user khác, đi vòng qua guard ở
        // RoleWriteService::save(). Xem docblock hàm đó.
        $roleIds = $this->roleWriteService->filterAssignableRoleIds(Auth::user(), $request->input('role_ids', []));
        $user = $this->repo->createAdmin($request->validated(), $roleIds);

        return response()->json(['data' => UserData::fromModel($user)], 201);
    }

    public function show(User $user)
    {
        abort_if((int) $user->type !== 1, 404);
        $user->load('roles');

        return response()->json(['data' => UserData::fromModel($user)]);
    }

    public function update(UserRequest $request, User $user)
    {
        abort_if((int) $user->type !== 1, 404);
        $roleIds = $this->roleWriteService->filterAssignableRoleIds(Auth::user(), $request->input('role_ids', []));
        $user = $this->repo->updateAdmin($user, $request->validated(), $roleIds);

        return response()->json(['data' => UserData::fromModel($user)]);
    }

    /**
     * Chống tự khoá tài khoản — mirror mt219 UserController::_ignoreDelete
     * (không cho xoá CHÍNH tài khoản đang đăng nhập). XOÁ MỀM (SoftDeletes)
     * — khôi phục được qua restore(), khác Role (xoá cứng).
     */
    public function destroy(User $user)
    {
        abort_if((int) $user->type !== 1, 404);

        if ((int) $user->id === (int) Auth::id()) {
            return response()->json(['message' => 'Không thể tự xoá tài khoản đang đăng nhập.'], 422);
        }

        $this->repo->deleteByIds([$user->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $user = $this->repo->restoreById((int) $id);
        abort_if($user === null, 404);

        return response()->json(['data' => UserData::fromModel($user)]);
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
            // Lọc bỏ chính mình khỏi mảng xoá hàng loạt — KHÔNG silent-fail
            // toàn bộ request, chỉ bỏ qua đúng 1 id đó (giống mt219: chặn
            // đúng thao tác tự xoá, không chặn cả batch).
            $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) Auth::id()));
            $affected = $this->repo->deleteByIds($ids);
        } else {
            $affected = $this->repo->restoreByIds($ids);
        }

        return response()->json(['affected' => $affected]);
    }
}
