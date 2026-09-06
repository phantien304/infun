<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Safety;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\SafetyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SafetyRepository extends QueryableRepository implements SafetyRepositoryInterface
{
    public function model(): string
    {
        return Safety::class;
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

    public function getForCms(int $id): ?Safety
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Safety $safety, array $data): Safety
    {
        $safety ??= new Safety();
        $safety->name        = $data['name'];
        $safety->background  = $data['background'] ?? null;
        $safety->sort_order  = (int) ($data['sort_order'] ?? 0);
        $safety->save();

        return $safety;
    }

    public function deleteByIds(array $ids): int
    {
        $affected = 0;
        foreach (Safety::whereIn('id', $ids)->get() as $safety) {
            $safety->delete();
            $affected++;
        }

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Safety
    {
        $safety = $this->resetModel()->withTrashed()->find($id);
        $safety?->restore();

        return $safety;
    }
}
