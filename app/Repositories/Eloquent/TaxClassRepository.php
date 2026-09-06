<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\TaxClass;
use App\Models\Entities\TaxRule;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\TaxClassRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaxClassRepository extends QueryableRepository implements TaxClassRepositoryInterface
{
    public function model(): string
    {
        return TaxClass::class;
    }

    public function getAll(): Collection
    {
        return TaxClass::query()->get();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['id', 'title'], true) ? $request->input('sort') : 'id';
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
                $q->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?TaxClass
    {
        return $this->resetModel()->withTrashed()->with('taxRules')->find($id);
    }

    public function saveFromCms(?TaxClass $taxClass, array $data): TaxClass
    {
        return DB::transaction(function () use ($taxClass, $data) {
            $taxClass ??= new TaxClass();
            $taxClass->title       = $data['title'];
            $taxClass->description = $data['description'] ?? null;
            $taxClass->save();

            TaxRule::where('tax_class_id', $taxClass->id)->delete();
            foreach ((array) ($data['tax_rules'] ?? []) as $item) {
                TaxRule::create([
                    'tax_class_id' => $taxClass->id,
                    'tax_rate_id'  => $item['tax_rate_id'],
                    'based'        => $item['based'],
                    'priority'     => $item['priority'] ?? 0,
                ]);
            }

            return $taxClass->load('taxRules');
        });
    }

    public function deleteByIds(array $ids): int
    {
        $affected = 0;
        foreach (TaxClass::whereIn('id', $ids)->get() as $taxClass) {
            $taxClass->delete();
            $affected++;
        }

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?TaxClass
    {
        $taxClass = $this->resetModel()->withTrashed()->find($id);
        $taxClass?->restore();

        return $taxClass?->load('taxRules');
    }
}
