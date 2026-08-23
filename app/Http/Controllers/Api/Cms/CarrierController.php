<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CarrierData;
use App\Http\Requests\Cms\CarrierRequest;
use App\Models\Entities\Carrier;
use App\Repositories\Interfaces\CarrierRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class CarrierController extends BaseCmsController
{
    protected string $permission = 'carrier';

    public function __construct(
        private readonly CarrierRepositoryInterface $repo
    ) {
    }

    /**
     * 2 CHẾ ĐỘ trên CÙNG 1 route (giống OrderStatusController::index()):
     *  - KHÔNG có query param nào (cách gọi hiện tại của order/form.jsx,
     *    components/order/Confirm.jsx — dùng `code` làm value) → giữ NGUYÊN
     *    hành vi cũ: mảng phẳng {id,code,name} từ cache, không phân trang.
     *  - CÓ ít nhất 1 param list chuẩn → màn CMS list mới (FormSearch+Pager).
     */
    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return CarrierData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->listAllCached()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'code' => $c->code,
                'name' => $c->name,
            ])
            ->values();

        return respondSuccess($data);
    }

    public function store(CarrierRequest $request)
    {
        $carrier = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(CarrierData::fromModel($carrier), 'carrier_created');
    }

    public function show(Carrier $carrier)
    {
        return respondSuccess(CarrierData::fromModel($carrier));
    }

    public function update(CarrierRequest $request, Carrier $carrier)
    {
        $carrier = $this->repo->saveFromCms($carrier, $request->validated());

        return respondSuccess(CarrierData::fromModel($carrier), 'carrier_updated');
    }

    public function destroy(Carrier $carrier)
    {
        $this->repo->deleteByIds([$carrier->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $carrier = $this->repo->restoreById((int) $id);
        abort_if($carrier === null, 404);

        return respondSuccess(CarrierData::fromModel($carrier), 'carrier_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
