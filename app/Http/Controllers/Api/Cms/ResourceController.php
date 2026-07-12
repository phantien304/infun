<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->boolean('list_for_product')) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => [
                'manufacture'  => $this->simple($this->manufacturerRepo->getAll()),
                'category'     => $this->categoryRepo->listWithDescription()
                    ->map(fn ($c) => ['id' => $c->id, 'title' => $c->description?->title ?? $c->title])
                    ->values(),
                'filter'       => $this->filterRepo->listWithValues()
                    ->map(fn ($f) => [
                        'id'            => $f->id,
                        'name'          => $f->description?->name ?? $f->name,
                        'filter_values' => ($f->filterValues ?? collect())->map(fn ($v) => [
                            'id'   => $v->id,
                            'name' => $v->description?->name ?? $v->name,
                        ])->values(),
                    ])->values(),
                'attribute'    => $this->attributeRepo->listWithValues()
                    ->map(fn ($a) => [
                        'id'               => $a->id,
                        'name'             => $a->description?->name ?? $a->name,
                        'attribute_values' => ($a->attributeValues ?? collect())->map(fn ($v) => [
                            'id'   => $v->id,
                            'name' => $v->description?->name ?? $v->name,
                        ])->values(),
                    ])->values(),
                'option'       => $this->optionRepo->listWithValues()
                    ->map(fn ($o) => [
                        'id'            => $o->id,
                        'name'          => $o->description?->name ?? $o->name,
                        'role'          => $o->role?->value,
                        'option_values' => ($o->optionValues ?? collect())->map(fn ($v) => [
                            'id'   => $v->id,
                            'name' => $v->description?->name ?? $v->name,
                        ])->values(),
                    ])->values(),
                'stock_status' => $this->simple($this->stockStatusRepo->listWithDescription()),
                'length_class' => $this->titled($this->lengthClassRepo->getAll()),
                'weight_class' => $this->titled($this->weightClassRepo->getAll()),
                'tax_class'    => $this->titled($this->taxClassRepo->getAll()),
                'user_group'   => $this->userGroupRepo->listWithDescription()
                    ->map(fn ($g) => ['id' => $g->id, 'name' => $g->description?->name ?? $g->name])
                    ->values(),
            ],
        ]);
    }

    /** {id, name} — nhan Collection da eager-load (description?->name hoac name). */
    private function simple(Collection $items): Collection
    {
        return $items->map(fn ($m) => [
            'id'   => $m->id,
            'name' => $m->description?->name ?? $m->name ?? null,
        ])->values();
    }

    /** {id, title} — nhan Collection da eager-load (description?->title hoac title). */
    private function titled(Collection $items): Collection
    {
        return $items->map(fn ($m) => [
            'id'    => $m->id,
            'title' => $m->description?->title ?? $m->title ?? null,
        ])->values();
    }
}
