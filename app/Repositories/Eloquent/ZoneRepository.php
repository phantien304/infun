<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Zone;
use App\Models\Entities\ZoneDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ZoneRepository extends QueryableRepository implements ZoneRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Zone::class;
    }

    protected function baseQuery(): Builder
    {
        return $this->resetModel()->newQuery()
            ->where('country_id', getCoreConfig('zones.country_id_default'));
    }

    protected function withRelations(): array
    {
        return ['description'];
    }

    public function listAllCached(): Collection
    {
        return $this->rememberSystemModels(
            setting('cache.zones'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.zones'));
    }

    public function nameById(int $id): string
    {
        return (string) ($this->resetModel()->newQuery()
            ->withTrashed()
            ->with('description')
            ->find($id)
            ?->description?->name ?? '');
    }

    // ===================== CMS (admin) =====================

    /**
     * Scope theo country_id mặc định (VN) — giống listAllCached/baseQuery().
     * Bảng zone import từ OpenCart có 4141 dòng khắp thế giới, chỉ ~64 dòng
     * VN thật sự dùng cho vận chuyển/checkout; quản lý full thế giới không
     * cần thiết cho CMS của shop VN-only.
     */
    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'zone_description.name' : 'zone.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->baseQuery()
            ->leftJoin('zone_description', function ($join) use ($lang) {
                $join->on('zone_description.zone_id', '=', 'zone.id')
                    ->where('zone_description.language_code', '=', $lang);
            })
            ->select('zone.*', 'zone_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('zone_description.name', 'like', '%' . $keyword . '%')
                    ->orWhere('zone.code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Zone
    {
        return $this->resetModel()->withTrashed()->with('descriptions')->find($id);
    }

    public function saveFromCms(?Zone $zone, array $data): Zone
    {
        return DB::transaction(function () use ($zone, $data) {
            $isNew = $zone === null;
            $zone ??= new Zone();
            if ($isNew) {
                $zone->country_id = getCoreConfig('zones.country_id_default');
            }
            $zone->code       = $data['code'] ?? null;
            $zone->ghn_id     = $this->nullableInt($data['ghn_id'] ?? null);
            $zone->ghn_code   = $this->nullableInt($data['ghn_code'] ?? null);
            $zone->vtp_id     = $this->nullableInt($data['vtp_id'] ?? null);
            $zone->vtp_code   = $data['vtp_code'] ?? null;
            $zone->sort_order = (int) ($data['sort_order'] ?? 0);
            $zone->status     = (int) ($data['status'] ?? 1);
            $zone->save();

            foreach ((array) ($data['zone_descriptions'] ?? []) as $item) {
                $code = $item['language_code'] ?? null;
                if (! $code) {
                    continue;
                }

                $desc = ZoneDescription::where('zone_id', $zone->id)
                    ->where('language_code', $code)
                    ->first();

                if (! empty($item['name'])) {
                    $desc ??= new ZoneDescription();
                    $desc->zone_id        = $zone->id;
                    $desc->language_code  = $code;
                    $desc->name           = $item['name'];
                    $desc->save();
                } elseif ($desc) {
                    $desc->delete();
                }
            }

            $this->flushCache();

            return $zone->load('descriptions');
        });
    }

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — Zone có
     * $destroyRelations = ['districts'], cần fire model event để cascade.
     */
    public function deleteByIds(array $ids): int
    {
        $rows = $this->resetModel()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->delete();
        }
        $this->flushCache();

        return $rows->count();
    }

    public function restoreByIds(array $ids): int
    {
        $affected = $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
        $this->flushCache();

        return $affected;
    }

    public function restoreById(int $id): ?Zone
    {
        $zone = $this->resetModel()->withTrashed()->find($id);
        $zone?->restore();
        $this->flushCache();

        return $zone?->load('descriptions');
    }
}
