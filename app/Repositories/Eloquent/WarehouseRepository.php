<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Warehouse;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WarehouseRepository extends QueryableRepository implements WarehouseRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Warehouse::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.warehouses'),
            fn () => $this->resetModel()->orderBy('priority')->orderBy('id')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.warehouses'), perLocale: false);
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['name', 'code', 'priority'], true) ? $request->input('sort') : 'priority';
        $order   = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function saveFromCms(?Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse ??= new Warehouse();
        $warehouse->fill($data);
        $warehouse->save();

        return $warehouse;
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Warehouse
    {
        $warehouse = $this->resetModel()->withTrashed()->find($id);
        $warehouse?->restore();

        return $warehouse;
    }
}
