<?php

namespace App\Repositories\Eloquent;

use App\Enums\OptionRole;
use App\Models\Entities\Option;
use App\Models\Entities\OptionDescription;
use App\Models\Entities\OptionValue;
use App\Models\Entities\OptionValueDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OptionRepository extends QueryableRepository implements OptionRepositoryInterface
{
    public function model(): string
    {
        return Option::class;
    }

    public function listWithValues(): Collection
    {
        return Option::with(['description', 'optionValues.description'])->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'option_description.name' : 'option.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('option_description', function ($join) use ($lang) {
                $join->on('option_description.option_id', '=', 'option.id')
                    ->where('option_description.language_code', '=', $lang);
            })
            ->select('option.*', 'option_description.name', 'option_description.name_display');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('option_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Option
    {
        return $this->resetModel()->withTrashed()
            ->with(['descriptions', 'optionValues.descriptions'])
            ->find($id);
    }

    public function saveFromCms(?Option $option, array $data): Option
    {
        return DB::transaction(function () use ($option, $data) {
            $option ??= new Option();
            $option->type       = (string) ($data['type'] ?? $option->type ?? 'text');
            $option->role       = OptionRole::fromInput($data['role'] ?? $option->role);
            $option->sort_order = (int) ($data['sort_order'] ?? 0);
            $option->save();

            foreach ((array) ($data['option_descriptions'] ?? []) as $item) {
                $this->saveOptionDescription($option->id, $item);
            }

            $this->saveOptionValues($option->id, (array) ($data['option_values'] ?? []));

            return $option->load(['descriptions', 'optionValues.descriptions']);
        });
    }

    protected function saveOptionDescription(int $optionId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = OptionDescription::where('option_id', $optionId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new OptionDescription();
            $desc->option_id     = $optionId;
            $desc->language_code = $code;
            $desc->name          = $item['name'];
            $desc->name_display  = $item['name_display'] ?? null;
            $desc->save();
        } elseif ($desc) {
            $desc->delete();
        }
    }

    protected function saveOptionValues(int $optionId, array $values): void
    {
        $existingIds = OptionValue::where('option_id', $optionId)->pluck('id')->all();
        $keptIds = [];

        foreach ($values as $item) {
            $value = ! empty($item['id'])
                ? OptionValue::where('option_id', $optionId)->find($item['id'])
                : null;
            $value ??= new OptionValue();

            $value->option_id  = $optionId;
            $value->image      = $item['image'] ?? null;
            $value->sort_order = (int) ($item['sort_order'] ?? 0);
            $value->save();

            $keptIds[] = $value->id;

            foreach ((array) ($item['option_value_descriptions'] ?? []) as $desc) {
                $this->saveOptionValueDescription($value->id, $desc);
            }
        }

        $removedIds = array_diff($existingIds, $keptIds);
        if ($removedIds) {
            // OptionValue dùng SoftDeletes — xoá ở đây chỉ set deleted_at,
            // không đụng FK product_variant_attribute/product_option_value
            // (30k+ dòng biến thể thật đang tham chiếu option_value_id).
            OptionValue::whereIn('id', $removedIds)->delete();
        }
    }

    protected function saveOptionValueDescription(int $optionValueId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = OptionValueDescription::where('option_value_id', $optionValueId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new OptionValueDescription();
            $desc->option_value_id = $optionValueId;
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

    public function restoreById(int $id): ?Option
    {
        $option = $this->resetModel()->withTrashed()->find($id);
        $option?->restore();

        return $option?->load(['descriptions', 'optionValues.descriptions']);
    }
}
