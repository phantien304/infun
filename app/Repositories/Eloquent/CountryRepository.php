<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Country;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CountryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CountryRepository extends QueryableRepository implements CountryRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Country::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.countries'),
            fn () => $this->resetModel()->orderBy('name')->get(),
            perLocale: false,
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.countries'), perLocale: false);
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['name', 'iso_code_2', 'iso_code_3'], true)
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
                    ->orWhere('iso_code_2', 'like', '%' . $keyword . '%')
                    ->orWhere('iso_code_3', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Country
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Country $country, array $data): Country
    {
        $country ??= new Country();
        $country->name               = $data['name'];
        $country->iso_code_2         = $data['iso_code_2'] ?? null;
        $country->iso_code_3         = $data['iso_code_3'] ?? null;
        $country->address_format     = $data['address_format'] ?? null;
        $country->postcode_required  = (int) ($data['postcode_required'] ?? 0);
        $country->status             = (int) ($data['status'] ?? 1);
        $country->save();

        $this->flushCache();

        return $country;
    }

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
        $affected = $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
        $this->flushCache();

        return $affected;
    }

    public function restoreById(int $id): ?Country
    {
        $country = $this->resetModel()->withTrashed()->find($id);
        $country?->restore();
        $this->flushCache();

        return $country;
    }
}
