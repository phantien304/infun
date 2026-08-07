<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CustomerData;
use App\Http\Requests\Cms\CustomerRequest;
use App\Models\Entities\User;
use App\Repositories\Interfaces\CustomerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Customer API cho CMS — permission slug 'customer'. Mirror UserController
 * (Phase 2.3, admin type=1) nhưng cho member (type=2 —
 * App\Enums\UserType::Member), theo yêu cầu user "Đầy đủ CRUD như màn User
 * admin" (docs/ROLE-PERMISSION-PLAN.md phần mở rộng sau Phase 4).
 *
 * Route param {customer} type-hint vẫn là App\Models\Entities\User (CÙNG
 * bảng `user`, khác entity CMS/permission) — Laravel implicit binding khớp
 * theo TÊN tham số route, không phải theo class, nên hoạt động bình thường
 * dù model không tên "Customer". abort_if(type !== 2, 404) chặn Admin lọt
 * qua route Customer (và ngược lại UserController đã chặn type !== 1).
 */
class CustomerController extends BaseCmsController
{
    protected string $permission = 'customer';

    public function __construct(
        private readonly CustomerRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return CustomerData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(CustomerRequest $request)
    {
        $customer = $this->repo->createCustomer($request->validated());

        return response()->json(['data' => CustomerData::fromModel($customer)], 201);
    }

    public function show(User $customer)
    {
        abort_if((int) $customer->type !== 2, 404);
        $customer->load(['userPhone', 'userGroup']);

        return response()->json(['data' => CustomerData::fromModel($customer)]);
    }

    public function update(CustomerRequest $request, User $customer)
    {
        abort_if((int) $customer->type !== 2, 404);
        $customer = $this->repo->updateCustomer($customer, $request->validated());

        return response()->json(['data' => CustomerData::fromModel($customer)]);
    }

    /** Xoá mềm (SoftDeletes) — khôi phục qua restore(). Chặn tự xoá chính mình, mirror UserController. */
    public function destroy(User $customer)
    {
        abort_if((int) $customer->type !== 2, 404);

        if ((int) $customer->id === (int) Auth::id()) {
            return response()->json(['message' => 'Không thể tự xoá tài khoản đang đăng nhập.'], 422);
        }

        $this->repo->deleteByIds([$customer->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $customer = $this->repo->restoreById((int) $id);
        abort_if($customer === null, 404);

        return response()->json(['data' => CustomerData::fromModel($customer)]);
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
            $ids      = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) Auth::id()));
            $affected = $this->repo->deleteByIds($ids);
        } else {
            $affected = $this->repo->restoreByIds($ids);
        }

        return response()->json(['affected' => $affected]);
    }
}
