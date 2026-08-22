<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Manufacturer;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ManufacturerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ManufacturerRepository extends QueryableRepository implements ManufacturerRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Manufacturer::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberSystemModels(
            setting('cache.manufacturers'),
            fn () => $this->listAll()
        );
    }

    public function getManufacturerDetail(int $id): ?Manufacturer
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()->find($id);
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.manufacturers'));
        \App\View\FragmentCache::forgetFacets();
    }

    public function getAll(): Collection
    {
        return $this->resetModel()->query()->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = $request->input('sort') === 'name' ? 'name' : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
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

    public function getForCms(int $id): ?Manufacturer
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Manufacturer $manufacturer, array $data): Manufacturer
    {
        $manufacturer ??= new Manufacturer();
        $manufacturer->name             = $data['name'];
        $manufacturer->image            = $data['image'] ?? null;
        $manufacturer->sort_order       = (int) ($data['sort_order'] ?? 0);
        $manufacturer->meta_title       = $data['meta_title'] ?? null;
        $manufacturer->meta_description = $data['meta_description'] ?? null;
        $manufacturer->save();

        return $manufacturer;
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Manufacturer
    {
        $manufacturer = $this->resetModel()->withTrashed()->find($id);
        $manufacturer?->restore();

        return $manufacturer;
    }
}
