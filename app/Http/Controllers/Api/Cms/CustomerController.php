<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CustomerData;
use App\Http\Requests\Cms\CustomerRequest;
use App\Models\Entities\User;
use App\Repositories\Interfaces\CustomerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\PaginatedDataCollection;

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

        return respondCreated(CustomerData::fromModel($customer), 'customer_created');
    }

    public function show(User $customer)
    {
        abort_if((int) $customer->type !== 2, 404);
        $customer->load(['userPhone', 'userGroup']);

        return respondSuccess(CustomerData::fromModel($customer));
    }

    public function update(CustomerRequest $request, User $customer)
    {
        abort_if((int) $customer->type !== 2, 404);
        $customer = $this->repo->updateCustomer($customer, $request->validated());

        return respondSuccess(CustomerData::fromModel($customer), 'customer_updated');
    }

    public function destroy(User $customer)
    {
        abort_if((int) $customer->type !== 2, 404);

        if ((int) $customer->id === (int) Auth::id()) {
            return respondUnprocessable('Không thể tự xoá tài khoản đang đăng nhập.');
        }

        $this->repo->deleteByIds([$customer->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $customer = $this->repo->restoreById((int) $id);
        abort_if($customer === null, 404);

        return respondSuccess(CustomerData::fromModel($customer), 'customer_restored');
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

        return respondSuccess(['affected' => $affected]);
    }
}
