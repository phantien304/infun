<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\WarehouseData;
use App\Http\Requests\Cms\WarehouseRequest;
use App\Models\Entities\Warehouse;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Warehouse API (REST) cho CMS — quản lý danh sách kho (đa kho). Mirror
 * MenuController: bảng `warehouse` KHÔNG đa ngôn ngữ, không vòng đồng bộ
 * *_descriptions.
 */
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

        return response()->json(['data' => WarehouseData::from($warehouse)], 201);
    }

    public function show(Warehouse $warehouse)
    {
        return response()->json(['data' => WarehouseData::from($warehouse)]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse = $this->repo->saveFromCms($warehouse, $request->validated());

        return response()->json(['data' => WarehouseData::from($warehouse)]);
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

        return response()->json(['data' => WarehouseData::from($warehouse)]);
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

        return response()->json(['affected' => $affected]);
    }
}
