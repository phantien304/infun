<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Carrier;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CarrierRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CarrierRepository extends QueryableRepository implements CarrierRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Carrier::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.carriers'),
            fn () => $this->resetModel()
                ->orderBy('sort_order', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.carriers'));
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['name', 'code', 'sort_order'], true)
            ? $request->input('sort')
            : 'id';
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
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Carrier
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Carrier $carrier, array $data): Carrier
    {
        $carrier ??= new Carrier();
        $carrier->code       = $data['code'];
        $carrier->name       = $data['name'];
        $carrier->image      = $data['image'] ?? null;
        $carrier->sort_order = (int) ($data['sort_order'] ?? 0);
        $carrier->save();

        $this->flushCache();

        return $carrier;
    }

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — xem lý do ở
     * interface ($destroyRelations = carrierOrderStatus).
     */
    public function deleteByIds(array $ids): int
    {
        $rows = $this->resetModel()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->delete();
        }
        $this->flushCache();

        return $rows->count();
    }

    public function restoreByIds(array $ids): int
    {
        $rows = $this->resetModel()->withTrashed()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            $row->restore();
        }
        $this->flushCache();

        return $rows->count();
    }

    public function restoreById(int $id): ?Carrier
    {
        $carrier = $this->resetModel()->withTrashed()->find($id);
        $carrier?->restore();
        $this->flushCache();

        return $carrier;
    }
}
