<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\District;
use App\Models\Entities\DistrictDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\DistrictRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DistrictRepository extends QueryableRepository implements DistrictRepositoryInterface
{
    public function model(): string
    {
        return District::class;
    }

    public function listByZone(int $zoneId): Collection
    {
        return $this->resetModel()->query()
            ->where('zone_id', $zoneId)
            ->with('description')
            ->orderBy('id')
            ->get();
    }

    public function nameById(int $id): string
    {
        return (string) ($this->resetModel()
            ->withTrashed()
            ->with('description')
            ->find($id)
            ?->description?->name ?? '');
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $zoneId  = (int) $request->input('zone_id');
        $sort    = $request->input('sort') === 'name' ? 'district_description.name' : 'district.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('district_description', function ($join) use ($lang) {
                $join->on('district_description.district_id', '=', 'district.id')
                    ->where('district_description.language_code', '=', $lang);
            })
            ->select('district.*', 'district_description.name');

        if ($zoneId > 0) {
            $query->where('district.zone_id', $zoneId);
        }

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('district_description.name', 'like', '%' . $keyword . '%')
                    ->orWhere('district.code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?District
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?District $district, array $data): District
    {
        return DB::transaction(function () use ($district, $data) {
            $district ??= new District();
            $district->zone_id      = (int) $data['zone_id'];
            $district->code         = $data['code'] ?? null;
            $district->ghn_id       = $this->nullableInt($data['ghn_id'] ?? null);
            $district->name_ghn     = $data['name_ghn'] ?? null;
            $district->vtp_id       = $this->nullableInt($data['vtp_id'] ?? null);
            $district->vtp_value    = $data['vtp_value'] ?? null;
            $district->type         = $this->nullableInt($data['type'] ?? null);
            $district->support_type = $this->nullableInt($data['support_type'] ?? null);
            $district->save();

            foreach ((array) ($data['district_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = DistrictDescription::where('district_id', $district->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['name'])) {
                    $desc ??= new DistrictDescription();
                    $desc->district_id    = $district->id;
                    $desc->language_code  = $code;
                    $desc->name           = $item['name'];
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $district->load('descriptions');
        });
    }

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — District có
     * $destroyRelations = ['wards'], cần fire model event để cascade.
     */
    public function deleteByIds(array $ids): int
    {
        $rows = $this->resetModel()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->delete();
        }

        return $rows->count();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?District
    {
        $district = $this->resetModel()->withTrashed()->find($id);
        $district?->restore();

        return $district?->load('descriptions');
    }
}
