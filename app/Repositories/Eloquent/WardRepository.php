<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Ward;
use App\Models\Entities\WardDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\WardRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WardRepository extends QueryableRepository implements WardRepositoryInterface
{
    public function model(): string
    {
        return Ward::class;
    }

    public function listByDistrict(int $districtId): Collection
    {
        return $this->resetModel()->query()
            ->where('district_id', $districtId)
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
        $lang       = $request->input('language_code') ?: $defaultLang;
        $districtId = (int) $request->input('district_id');
        $sort       = $request->input('sort') === 'name' ? 'ward_description.name' : 'ward.id';
        $order      = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted    = (int) $request->input('deleted_at', 1);
        $keyword    = trim((string) $request->input('keyword', ''));
        $perPage    = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('ward_description', function ($join) use ($lang) {
                $join->on('ward_description.ward_id', '=', 'ward.id')
                    ->where('ward_description.language_code', '=', $lang);
            })
            ->select('ward.*', 'ward_description.name');

        if ($districtId > 0) {
            $query->where('ward.district_id', $districtId);
        }

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('ward_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Ward
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?Ward $ward, array $data): Ward
    {
        return DB::transaction(function () use ($ward, $data) {
            $ward ??= new Ward();
            $ward->district_id = (int) $data['district_id'];
            $ward->ghn_id      = $data['ghn_id'] ?? null;
            $ward->vtp_id      = $this->nullableInt($data['vtp_id'] ?? null);
            $ward->name_vtp    = $data['name_vtp'] ?? null;
            $ward->name_ghn    = $data['name_ghn'] ?? null;
            $ward->note        = $data['note'] ?? null;
            $ward->save();

            foreach ((array) ($data['ward_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = WardDescription::where('ward_id', $ward->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['name'])) {
                    $desc ??= new WardDescription();
                    $desc->ward_id        = $ward->id;
                    $desc->language_code  = $code;
                    $desc->name           = $item['name'];
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            return $ward->load('descriptions');
        });
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Ward
    {
        $ward = $this->resetModel()->withTrashed()->find($id);
        $ward?->restore();

        return $ward?->load('descriptions');
    }
}
