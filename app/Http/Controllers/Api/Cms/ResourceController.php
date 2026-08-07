<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        // Dropdown cho setting/detail.jsx (mirror mt219 getListResource({list_for_setting:true})).
        // user_group/weight_class/length_class/category KHÔNG có CRUD route riêng ở
        // rcms (đợi đợt convert sau) — giống cách product form đã lấy qua đây.
        if ($request->boolean('list_for_setting')) {
            return respondSuccess([
                'user_group'   => $this->userGroupRepo->listWithDescription()
                    ->map(fn ($g) => ['id' => $g->id, 'name' => $g->description?->name ?? $g->name])
                    ->values(),
                'weight_class' => $this->titled($this->weightClassRepo->getAll()),
                'length_class' => $this->titled($this->lengthClassRepo->getAll()),
                'category'     => $this->categoryRepo->listWithDescription()
                    ->map(fn ($c) => ['id' => $c->id, 'title' => $c->description?->title ?? $c->title])
                    ->values(),
            ]);
        }

        if (! $request->boolean('list_for_product')) {
            return respondSuccess([]);
        }

        return respondSuccess([
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
            'warehouse'    => $this->warehouseRepo->listAllCached()
                ->map(fn ($w) => [
                    'id'          => $w->id,
                    'code'        => $w->code,
                    'name'        => $w->name,
                    'is_active'   => (bool) $w->is_active,
                    'is_sellable' => (bool) $w->is_sellable,
                ])->values(),
        ]);
    }

    private function simple(Collection $items): Collection
    {
        return $items->map(fn ($m) => [
            'id'   => $m->id,
            'name' => $m->description?->name ?? $m->name ?? null,
        ])->values();
    }

    private function titled(Collection $items): Collection
    {
        return $items->map(fn ($m) => [
            'id'    => $m->id,
            'title' => $m->description?->title ?? $m->title ?? null,
        ])->values();
    }
}
