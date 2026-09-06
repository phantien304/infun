<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\GeoZoneData;
use App\Http\Requests\Cms\GeoZoneRequest;
use App\Models\Entities\GeoZone;
use App\Repositories\Interfaces\GeoZoneRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class GeoZoneController extends BaseCmsController
{
    protected string $permission = 'geo-zone';

    public function __construct(
        private readonly GeoZoneRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        if ($request->hasAny(['page', 'per_page', 'keyword', 'deleted_at', 'sort', 'order'])) {
            return GeoZoneData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
        }

        $data = $this->repo->getAll()
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
            ->values();

        return respondSuccess($data);
    }

    public function store(GeoZoneRequest $request)
    {
        $geoZone = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(GeoZoneData::fromModel($geoZone), 'geo_zone_created');
    }

    public function show(GeoZone $geoZone)
    {
        return respondSuccess(GeoZoneData::fromModel($geoZone->load('zoneToGeoZones')));
    }

    public function update(GeoZoneRequest $request, GeoZone $geoZone)
    {
        $geoZone = $this->repo->saveFromCms($geoZone, $request->validated());

        return respondSuccess(GeoZoneData::fromModel($geoZone), 'geo_zone_updated');
    }

    public function destroy(GeoZone $geoZone)
    {
        $this->repo->deleteByIds([$geoZone->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $geoZone = $this->repo->restoreById((int) $id);
        abort_if($geoZone === null, 404);

        return respondSuccess(GeoZoneData::fromModel($geoZone), 'geo_zone_restored');
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
