<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Entities\Attribute;
use App\Models\Entities\Category;
use App\Models\Entities\Filter;
use App\Models\Entities\LengthClass;
use App\Models\Entities\Manufacturer;
use App\Models\Entities\Option;
use App\Models\Entities\StockStatus;
use App\Models\Entities\TaxClass;
use App\Models\Entities\UserGroup;
use App\Models\Entities\WeightClass;
use Illuminate\Http\Request;

/**
 * Cung cấp các "nguồn" (dropdown) cho form CMS.
 * GET /rcms/resource?list_for_product=true  → bundle cho product form.
 * -----------------------------------------------------------
 * Output (khớp infun_cms form đọc):
 *   manufacture, category[{id,title}], filter[{id,name,filter_values[{id,name}]}],
 *   attribute[{id,name,attribute_values[{id,name}]}],
 *   option[{id,name,role,option_values[{id,name}]}],
 *   stock_status, length_class, weight_class, tax_class, user_group.
 *
 * Best-effort: tên relation (filterValues/attributeValues/optionValues) +
 * description name/title cần verify theo schema khi chạy.
 * -----------------------------------------------------------
 */
class ResourceController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->boolean('list_for_product')) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => [
                'manufacture'  => $this->simple(Manufacturer::query()),
                'category'     => Category::with('description')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'title' => $c->description?->title ?? $c->title])
                    ->values(),
                'filter'       => Filter::with(['description', 'filterValues.description'])->get()
                    ->map(fn ($f) => [
                        'id'            => $f->id,
                        'name'          => $f->description?->name ?? $f->name,
                        'filter_values' => ($f->filterValues ?? collect())->map(fn ($v) => [
                            'id'   => $v->id,
                            'name' => $v->description?->name ?? $v->name,
                        ])->values(),
                    ])->values(),
                'attribute'    => Attribute::with(['description', 'attributeValues.description'])->get()
                    ->map(fn ($a) => [
                        'id'               => $a->id,
                        'name'             => $a->description?->name ?? $a->name,
                        'attribute_values' => ($a->attributeValues ?? collect())->map(fn ($v) => [
                            'id'   => $v->id,
                            'name' => $v->description?->name ?? $v->name,
                        ])->values(),
                    ])->values(),
                'option'       => Option::with(['description', 'optionValues.description'])->get()
                    ->map(fn ($o) => [
                        'id'            => $o->id,
                        'name'          => $o->description?->name ?? $o->name,
                        'role'          => $o->role,
                        'option_values' => ($o->optionValues ?? collect())->map(fn ($v) => [
                            'id'   => $v->id,
                            'name' => $v->description?->name ?? $v->name,
                        ])->values(),
                    ])->values(),
                'stock_status' => $this->simple(StockStatus::with('description'), true),
                'length_class' => $this->titled(LengthClass::query()),
                'weight_class' => $this->titled(WeightClass::query()),
                'tax_class'    => $this->titled(TaxClass::query()),
                'user_group'   => UserGroup::with('description')->get()
                    ->map(fn ($g) => ['id' => $g->id, 'name' => $g->description?->name ?? $g->name])
                    ->values(),
            ],
        ]);
    }

    /** {id, name} — model có cột name hoặc description?->name. */
    private function simple($query, bool $withDesc = false)
    {
        $items = $withDesc ? $query->get() : $query->get();
        return $items->map(fn ($m) => [
            'id'   => $m->id,
            'name' => $m->description?->name ?? $m->name ?? null,
        ])->values();
    }

    /** {id, title} — class cân/độ dài/thuế (description?->title hoặc cột title). */
    private function titled($query)
    {
        return $query->get()->map(fn ($m) => [
            'id'    => $m->id,
            'title' => $m->description?->title ?? $m->title ?? null,
        ])->values();
    }
}
