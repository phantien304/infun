<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\GeoZone;
use App\Models\Entities\ZoneToGeoZone;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\GeoZoneRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoZoneRepository extends QueryableRepository implements GeoZoneRepositoryInterface
{
    public function model(): string
    {
        return GeoZone::class;
    }

    public function getAll(): Collection
    {
        return GeoZone::query()->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['id', 'name'], true) ? $request->input('sort') : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?GeoZone
    {
        return $this->resetModel()->withTrashed()->with('zoneToGeoZones')->find($id);
    }

    public function saveFromCms(?GeoZone $geoZone, array $data): GeoZone
    {
        return DB::transaction(function () use ($geoZone, $data) {
            $geoZone ??= new GeoZone();
            $geoZone->name        = $data['name'];
            $geoZone->description = $data['description'] ?? null;
            $geoZone->save();

            ZoneToGeoZone::where('geo_zone_id', $geoZone->id)->delete();
            foreach ((array) ($data['zone_to_geo_zones'] ?? []) as $item) {
                if (empty($item['country_id']) && empty($item['zone_id'])) {
                    continue;
                }
                ZoneToGeoZone::create([
                    'geo_zone_id' => $geoZone->id,
                    'country_id'  => $item['country_id'] ?? null,
                    'zone_id'     => $item['zone_id'] ?? null,
                ]);
            }

            return $geoZone->load('zoneToGeoZones');
        });
    }

    public function deleteByIds(array $ids): int
    {
        $affected = 0;
        foreach (GeoZone::whereIn('id', $ids)->get() as $geoZone) {
            $geoZone->delete();
            $affected++;
        }

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?GeoZone
    {
        $geoZone = $this->resetModel()->withTrashed()->find($id);
        $geoZone?->restore();

        return $geoZone?->load('zoneToGeoZones');
    }
}
