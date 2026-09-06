<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Effect;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\EffectRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EffectRepository extends QueryableRepository implements EffectRepositoryInterface
{
    public function model(): string
    {
        return Effect::class;
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['id', 'name', 'sort_order'], true) ? $request->input('sort') : 'id';
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
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Effect
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Effect $effect, array $data): Effect
    {
        $effect ??= new Effect();
        $effect->name        = $data['name'];
        $effect->icon        = $data['icon'] ?? null;
        $effect->image_icon  = $data['image_icon'] ?? null;
        $effect->sort_order  = (int) ($data['sort_order'] ?? 0);
        $effect->save();

        return $effect;
    }

    public function deleteByIds(array $ids): int
    {
        $affected = 0;
        foreach (Effect::whereIn('id', $ids)->get() as $effect) {
            $effect->delete();
            $affected++;
        }

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Effect
    {
        $effect = $this->resetModel()->withTrashed()->find($id);
        $effect?->restore();

        return $effect;
    }
}
