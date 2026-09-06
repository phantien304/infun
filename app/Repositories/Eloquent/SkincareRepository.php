<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Skincare;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\SkincareRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SkincareRepository extends QueryableRepository implements SkincareRepositoryInterface
{
    public function model(): string
    {
        return Skincare::class;
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['id', 'name'], true) ? $request->input('sort') : 'id';
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

    public function getForCms(int $id): ?Skincare
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Skincare $skincare, array $data): Skincare
    {
        $skincare ??= new Skincare();
        $skincare->name        = $data['name'];
        $skincare->icon        = $data['icon'] ?? null;
        $skincare->image_icon  = $data['image_icon'] ?? null;
        $skincare->save();

        return $skincare;
    }

    public function deleteByIds(array $ids): int
    {
        $affected = 0;
        foreach (Skincare::whereIn('id', $ids)->get() as $skincare) {
            $skincare->delete();
            $affected++;
        }

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Skincare
    {
        $skincare = $this->resetModel()->withTrashed()->find($id);
        $skincare?->restore();

        return $skincare;
    }
}
