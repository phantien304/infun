<?php

namespace App\Http\Controllers\Api\Cms\Catalog;

use App\Data\Cms\ManufacturerData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\ManufacturerRequest;
use App\Models\Entities\Manufacturer;
use App\Repositories\Interfaces\ManufacturerRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class ManufacturerController extends BaseCmsController
{
    protected string $permission = 'manufacturer';

    public function __construct(
        private readonly ManufacturerRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return ManufacturerData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(ManufacturerRequest $request)
    {
        $manufacturer = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(ManufacturerData::fromModel($manufacturer), 'manufacturer_created');
    }

    public function show(Manufacturer $manufacturer)
    {
        return respondSuccess(ManufacturerData::fromModel($manufacturer));
    }

    public function update(ManufacturerRequest $request, Manufacturer $manufacturer)
    {
        $manufacturer = $this->repo->saveFromCms($manufacturer, $request->validated());

        return respondSuccess(ManufacturerData::fromModel($manufacturer), 'manufacturer_updated');
    }

    public function destroy(Manufacturer $manufacturer)
    {
        $this->repo->deleteByIds([$manufacturer->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $manufacturer = $this->repo->restoreById((int) $id);
        abort_if($manufacturer === null, 404);

        return respondSuccess(ManufacturerData::fromModel($manufacturer), 'manufacturer_restored');
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
