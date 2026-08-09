<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Role;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\RoleRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleRepository extends QueryableRepository implements RoleRepositoryInterface
{
    private const GUARD = 'web';

    public function model(): string
    {
        return Role::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = $request->input('sort') === 'name' ? 'name' : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = (new Role(['guard_name' => self::GUARD]))
            ->newQuery()
            ->where('guard_name', self::GUARD)
            ->withCount(['permissions', 'users']);

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Role
    {
        return $this->resetModel()->with('permissions')->find($id);
    }
}
