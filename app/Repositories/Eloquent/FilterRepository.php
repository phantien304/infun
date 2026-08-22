<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\FilterValueInUseException;
use App\Models\Entities\Filter;
use App\Models\Entities\FilterDescription;
use App\Models\Entities\FilterValue;
use App\Models\Entities\FilterValueDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\FilterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FilterRepository extends QueryableRepository implements FilterRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Filter::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberSystemModels(
            setting('cache.filters'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.filters'));
        \App\View\FragmentCache::forgetFacets();
    }

    protected function withRelations(): array
    {
        return [
            'description',
            'filterValues.description'
        ];
    }

    public function listWithValues(): \Illuminate\Support\Collection
    {
        return $this->resetModel()->with(['description', 'filterValues.description'])->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'filter_description.name' : 'filter.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('filter_description', function ($join) use ($lang) {
                $join->on('filter_description.filter_id', '=', 'filter.id')
                    ->where('filter_description.language_code', '=', $lang);
            })
            ->select('filter.*', 'filter_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('filter_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Filter
    {
        return $this->resetModel()->withTrashed()
            ->with(['descriptions', 'filterValues.descriptions'])
            ->find($id);
    }

    public function saveFromCms(?Filter $filter, array $data): Filter
    {
        return DB::transaction(function () use ($filter, $data) {
            $filter ??= new Filter();
            $filter->sort_order = (int) ($data['sort_order'] ?? 0);
            $filter->save();

            foreach ((array) ($data['filter_descriptions'] ?? []) as $item) {
                $this->saveFilterDescription($filter->id, $item);
            }

            $this->saveFilterValues($filter->id, (array) ($data['filter_values'] ?? []));

            return $filter->load(['descriptions', 'filterValues.descriptions']);
        });
    }

    protected function saveFilterDescription(int $filterId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = FilterDescription::where('filter_id', $filterId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new FilterDescription();
            $desc->filter_id     = $filterId;
            $desc->language_code = $code;
            $desc->name          = $item['name'];
            $desc->save();
        } elseif ($desc) {
            $desc->delete();
        }
    }

    protected function saveFilterValues(int $filterId, array $values): void
    {
        $existingIds = FilterValue::where('filter_id', $filterId)->pluck('id')->all();
        $keptIds = [];

        foreach ($values as $item) {
            $value = ! empty($item['id'])
                ? FilterValue::where('filter_id', $filterId)->find($item['id'])
                : null;
            $value ??= new FilterValue();

            $value->filter_id  = $filterId;
            $value->sort_order = (int) ($item['sort_order'] ?? 0);
            $value->save();

            $keptIds[] = $value->id;

            foreach ((array) ($item['filter_value_descriptions'] ?? []) as $desc) {
                $this->saveFilterValueDescription($value->id, $desc);
            }
        }

        $removedIds = array_diff($existingIds, $keptIds);
        if ($removedIds) {
            try {
                FilterValue::whereIn('id', $removedIds)->delete();
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    throw new FilterValueInUseException();
                }
                throw $e;
            }
        }
    }

    protected function saveFilterValueDescription(int $filterValueId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = FilterValueDescription::where('filter_value_id', $filterValueId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new FilterValueDescription();
            $desc->filter_value_id = $filterValueId;
            $desc->language_code   = $code;
            $desc->name            = $item['name'];
            $desc->save();
        } elseif ($desc) {
            $desc->delete();
        }
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Filter
    {
        $filter = $this->resetModel()->withTrashed()->find($id);
        $filter?->restore();

        return $filter?->load(['descriptions', 'filterValues.descriptions']);
    }
}
