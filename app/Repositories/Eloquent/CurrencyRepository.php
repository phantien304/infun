<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Currency;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CurrencyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CurrencyRepository extends QueryableRepository implements CurrencyRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Currency::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.currencies'),
            fn () => $this->resetModel()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.currencies'));
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['code', 'title', 'sort_order'], true)
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
                $q->where('code', 'like', '%' . $keyword . '%')
                    ->orWhere('title', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Currency
    {
        return $this->resetModel()->withTrashed()->find($id);
    }

    public function saveFromCms(?Currency $currency, array $data): Currency
    {
        $currency ??= new Currency();
        $currency->code          = $data['code'];
        $currency->title         = $data['title'] ?? null;
        $currency->unit          = $data['unit'] ?? null;
        $currency->value         = (float) ($data['value'] ?? 0);
        $currency->symbol_left   = $data['symbol_left'] ?? null;
        $currency->symbol_right  = $data['symbol_right'] ?? null;
        $currency->decimal_place = (int) ($data['decimal_place'] ?? 0);
        $currency->sort_order    = (int) ($data['sort_order'] ?? 0);
        $currency->save();

        $this->flushCache();

        return $currency;
    }

    public function idsBlockedFromDelete(array $ids): array
    {
        $activeCount = $this->resetModel()->count();
        if ($activeCount - count($ids) >= 1) {
            return [];
        }

        return $ids;
    }

    public function deleteByIds(array $ids): int
    {
        $affected = $this->resetModel()->whereIn('id', $ids)->delete();
        $this->flushCache();

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        $affected = $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
        $this->flushCache();

        return $affected;
    }

    public function restoreById(int $id): ?Currency
    {
        $currency = $this->resetModel()->withTrashed()->find($id);
        $currency?->restore();
        $this->flushCache();

        return $currency;
    }
}
