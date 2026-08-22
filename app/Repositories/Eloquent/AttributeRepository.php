<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Attribute;
use App\Models\Entities\AttributeDescription;
use App\Models\Entities\AttributeValue;
use App\Models\Entities\AttributeValueDescription;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AttributeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttributeRepository extends QueryableRepository implements AttributeRepositoryInterface
{
    public function model(): string
    {
        return Attribute::class;
    }

    public function listWithValues(): Collection
    {
        return Attribute::with(['description', 'attributeValues.description'])->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $sort    = $request->input('sort') === 'name' ? 'attribute_description.name' : 'attribute.id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()
            ->leftJoin('attribute_description', function ($join) use ($lang) {
                $join->on('attribute_description.attribute_id', '=', 'attribute.id')
                    ->where('attribute_description.language_code', '=', $lang);
            })
            ->select('attribute.*', 'attribute_description.name');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('attribute_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Attribute
    {
        return $this->resetModel()->withTrashed()
            ->with(['descriptions', 'attributeValues.descriptions'])
            ->find($id);
    }

    public function saveFromCms(?Attribute $attribute, array $data): Attribute
    {
        return DB::transaction(function () use ($attribute, $data) {
            $attribute ??= new Attribute();
            $attribute->sort_order = (int) ($data['sort_order'] ?? 0);
            $attribute->save();

            foreach ((array) ($data['attribute_descriptions'] ?? []) as $item) {
                $this->saveAttributeDescription($attribute->id, $item);
            }

            $this->saveAttributeValues($attribute->id, (array) ($data['attribute_values'] ?? []));

            return $attribute->load(['descriptions', 'attributeValues.descriptions']);
        });
    }

    protected function saveAttributeDescription(int $attributeId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = AttributeDescription::where('attribute_id', $attributeId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new AttributeDescription();
            $desc->attribute_id  = $attributeId;
            $desc->language_code = $code;
            $desc->name          = $item['name'];
            $desc->save();
        } elseif ($desc) {
            $desc->delete();
        }
    }

    protected function saveAttributeValues(int $attributeId, array $values): void
    {
        $existingIds = AttributeValue::where('attribute_id', $attributeId)->pluck('id')->all();
        $keptIds = [];

        foreach ($values as $item) {
            $value = ! empty($item['id'])
                ? AttributeValue::where('attribute_id', $attributeId)->find($item['id'])
                : null;
            $value ??= new AttributeValue();

            $value->attribute_id = $attributeId;
            $value->sort_order   = (int) ($item['sort_order'] ?? 0);
            $value->save();

            $keptIds[] = $value->id;

            foreach ((array) ($item['attribute_value_descriptions'] ?? []) as $desc) {
                $this->saveAttributeValueDescription($value->id, $desc);
            }
        }

        $removedIds = array_diff($existingIds, $keptIds);
        if ($removedIds) {
            AttributeValue::whereIn('id', $removedIds)->delete();
        }
    }

    protected function saveAttributeValueDescription(int $attributeValueId, array $item): void
    {
        $code = $item['language_code'] ?? null;
        if (! $code) {
            return;
        }

        $desc = AttributeValueDescription::where('attribute_value_id', $attributeValueId)
            ->where('language_code', $code)
            ->first();

        if (! empty($item['name'])) {
            $desc ??= new AttributeValueDescription();
            $desc->attribute_value_id = $attributeValueId;
            $desc->language_code      = $code;
            $desc->name               = $item['name'];
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

    public function restoreById(int $id): ?Attribute
    {
        $attribute = $this->resetModel()->withTrashed()->find($id);
        $attribute?->restore();

        return $attribute?->load(['descriptions', 'attributeValues.descriptions']);
    }
}
