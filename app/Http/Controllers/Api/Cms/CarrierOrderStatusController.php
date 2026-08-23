<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\CarrierOrderStatusData;
use App\Http\Requests\Cms\CarrierOrderStatusRequest;
use App\Models\Entities\CarrierOrderStatus;
use App\Repositories\Interfaces\CarrierOrderStatusRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class CarrierOrderStatusController extends BaseCmsController
{
    protected string $permission = 'carrier-order-status';

    public function __construct(
        private readonly CarrierOrderStatusRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return CarrierOrderStatusData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(CarrierOrderStatusRequest $request)
    {
        $status = $this->repo->saveFromCms(null, $request->validated());
        $status = $this->repo->getForCms($status->id);

        return respondCreated(CarrierOrderStatusData::fromModel($status), 'carrier_order_status_created');
    }

    public function show($id)
    {
        $status = $this->repo->getForCms((int) $id);
        abort_if($status === null, 404);

        return respondSuccess(CarrierOrderStatusData::fromModel($status));
    }

    public function update(CarrierOrderStatusRequest $request, CarrierOrderStatus $carrierOrderStatus)
    {
        $status = $this->repo->saveFromCms($carrierOrderStatus, $request->validated());
        $status = $this->repo->getForCms($status->id);

        return respondSuccess(CarrierOrderStatusData::fromModel($status), 'carrier_order_status_updated');
    }

    public function destroy(CarrierOrderStatus $carrierOrderStatus)
    {
        $this->repo->deleteByIds([$carrierOrderStatus->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $status = $this->repo->restoreById((int) $id);
        abort_if($status === null, 404);

        return respondSuccess(CarrierOrderStatusData::fromModel($status), 'carrier_order_status_restored');
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
