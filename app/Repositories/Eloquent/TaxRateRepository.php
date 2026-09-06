<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\TaxRate;
use App\Models\Entities\TaxRateToUserGroup;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\TaxRateRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaxRateRepository extends QueryableRepository implements TaxRateRepositoryInterface
{
    public function model(): string
    {
        return TaxRate::class;
    }

    public function getAll(): Collection
    {
        return TaxRate::query()->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['id', 'name', 'rate'], true) ? $request->input('sort') : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', 1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query()->with('geoZone');

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

    public function getForCms(int $id): ?TaxRate
    {
        return $this->resetModel()->withTrashed()->with(['geoZone', 'taxRateToUserGroups'])->find($id);
    }

    public function saveFromCms(?TaxRate $taxRate, array $data): TaxRate
    {
        return DB::transaction(function () use ($taxRate, $data) {
            $taxRate ??= new TaxRate();
            $taxRate->geo_zone_id = $data['geo_zone_id'];
            $taxRate->name        = $data['name'];
            $taxRate->rate        = $data['rate'];
            $taxRate->type        = $data['type'];
            $taxRate->save();

            TaxRateToUserGroup::where('tax_rate_id', $taxRate->id)->delete();
            foreach ((array) ($data['tax_rate_to_user_groups'] ?? []) as $userGroupId) {
                TaxRateToUserGroup::create([
                    'tax_rate_id'    => $taxRate->id,
                    'user_group_id'  => $userGroupId,
                ]);
            }

            return $taxRate->load(['geoZone', 'taxRateToUserGroups']);
        });
    }

    public function deleteByIds(array $ids): int
    {
        $affected = 0;
        foreach (TaxRate::whereIn('id', $ids)->get() as $taxRate) {
            $taxRate->delete();
            $affected++;
        }

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?TaxRate
    {
        $taxRate = $this->resetModel()->withTrashed()->find($id);
        $taxRate?->restore();

        return $taxRate?->load(['geoZone', 'taxRateToUserGroups']);
    }
}
