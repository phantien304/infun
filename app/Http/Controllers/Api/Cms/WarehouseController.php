<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\WarehouseData;
use App\Http\Requests\Cms\WarehouseRequest;
use App\Models\Entities\Warehouse;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class WarehouseController extends BaseCmsController
{
    protected string $permission = 'warehouse';

    public function __construct(
        private readonly WarehouseRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return WarehouseData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(WarehouseRequest $request)
    {
        $warehouse = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(WarehouseData::from($warehouse), 'warehouse_created');
    }

    public function show(Warehouse $warehouse)
    {
        return respondSuccess(WarehouseData::from($warehouse));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse = $this->repo->saveFromCms($warehouse, $request->validated());

        return respondSuccess(WarehouseData::from($warehouse), 'warehouse_updated');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->repo->deleteByIds([$warehouse->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $warehouse = $this->repo->restoreById((int) $id);
        abort_if($warehouse === null, 404);

        return respondSuccess(WarehouseData::from($warehouse), 'warehouse_restored');
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

        return respondSuccess(['affected' => $affected], 'warehouse_bulk_action_completed');
    }
}
