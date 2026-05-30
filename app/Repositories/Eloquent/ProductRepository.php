<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Product;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ProductRepository extends QueryableRepository implements ProductRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Product::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('category_id', function (Builder $q, $value) {
                $q->whereHas('productCategories', fn ($qq) => $qq->whereIn('category_id', (array) $value));
            }),

            AllowedFilter::callback('manufacturer_id', function (Builder $q, $value) {
                $q->whereIn('product.manufacturer_id', (array) $value);
            }),

            AllowedFilter::callback('filter_value_id', function (Builder $q, $value) {
                $q->whereHas('productFilters', fn ($qq) => $qq->whereIn('filter_value_id', (array) $value));
            }),

            AllowedFilter::callback('price_min', fn () => null),
            AllowedFilter::callback('price_max', fn () => null),

            AllowedFilter::callback('in_stock', function (Builder $q, $value) {
                $values = collect((array) $value)
                    ->map(fn ($v) => (string) $v)
                    ->filter(fn ($v) => $v === '0' || $v === '1')
                    ->unique()
                    ->values();

                if ($values->count() !== 1) {
                    return;
                }

                $values->first() === '1'
                    ? $q->where('product.quantity', '>', 0)
                    : $q->where(fn ($qq) => $qq->where('product.quantity', '<=', 0)
                        ->orWhereNull('product.quantity'));
            }),

            AllowedFilter::callback('search', function (Builder $q, $value) {
                $q->where(function (Builder $qq) use ($value) {
                    $qq->where('product_description.name', 'like', "%{$value}%")
                        ->orWhere('product_description.description', 'like', "%{$value}%");
                });
            }),
        ];
    }

    protected function allowedSorts(): array
    {
        return [
            AllowedSort::callback('price', fn (Builder $q, bool $descending) => $q->orderByEffectivePrice($descending ? 'desc' : 'asc')),
            AllowedSort::field('created_at', 'product.created_at'),
            AllowedSort::field('viewed', 'product.viewed'),
            AllowedSort::field('rating', 'product.rating'),
            AllowedSort::field('name', 'product_description.name'),
        ];
    }

    protected function defaultSort(): string
    {
        return '-product.created_at';
    }

    protected function sortMenu(): array
    {
        return ['-created_at', 'created_at', 'price', '-price', 'name', '-name', '-viewed'];
    }

    protected function baseQuery(): Builder
    {
        return $this->model->newQuery()
            ->select('product.*')
            ->leftJoin('product_description', function ($join) {
                $join->on('product_description.product_id', '=', 'product.id')
                    ->where('product_description.language_code', app()->getLocale());
            });
    }

    protected function beforeBuild(Builder $query): Builder
    {
        $query = $this->clientScope($query);

        $min = self::normalizePrice(request()->input('filter.price_min'));
        $max = self::normalizePrice(request()->input('filter.price_max'));
        if ($min !== null || $max !== null) {
            $query->effectivePriceBetween($min, $max);
        }

        return $query;
    }

    protected function withRelations(): array
    {
        $relations = $this->clientRelations();
        $selected = (array) request()->input('filter.filter_value_id', []);
        if (! empty($selected)) {
            $relations[] = ['productFilters' => fn ($q) => $q->whereIn('filter_value_id', $selected)];
            $relations[] = 'productFilters.filterValue.description';
        }

        return $relations;
    }

    public function getByIds(array $productIds)
    {
        return $this->resetModel()->whereIn('id', $productIds)->get();
    }

    public function getListSpecial(?Request $request = null): LengthAwarePaginator
    {
        return $this->list($request, null, fn (Builder $q) => $q->hasActiveSpecial());
    }

    public function getProductSpecialLatest(int $limit = 8)
    {
        return $this->rememberCacheTagged(
            [getCoreConfig('cache.product_root')],
            $this->specialLatestCacheKey($limit),
            fn () => $this->clientQuery()
                ->hasActiveSpecial()
                ->orderBy('product.sort_order', 'DESC')
                ->orderBy('product.created_at', 'DESC')
                ->take($limit)
                ->get(),
            getCoreConfig('time.cache')
        );
    }

    public function getProductFeature(int $limit = 6)
    {
        return $this->clientQuery()
            ->where('product.badge', 'feature')
            ->orderBy('product.id', 'DESC')
            ->take($limit)
            ->get();
    }

    public function getProductLatest(int $limit = 6)
    {
        return $this->rememberCacheTagged(
            [getCoreConfig('cache.product_root'), getCoreConfig('cache.product_latest')],
            $this->latestCacheKey($limit),
            fn () => $this->clientQuery()
                ->orderBy('product.created_at', 'DESC')
                ->take($limit)
                ->get(),
            getCoreConfig('time.cache')
        );
    }

    public function getProductRelated(array $productIds)
    {
        if (empty($productIds)) {
            return collect();
        }

        $productIds = array_values(array_filter(array_map('intval', $productIds)));

        $sorted = $productIds;
        sort($sorted);
        $key = implode('_', [getCoreConfig('cache.product_related'), getUserGroupId(), getUserType(), md5(implode(',', $sorted))]).'_';

        return $this->rememberCacheTagged(
            [getCoreConfig('cache.product_root')],
            $key,
            fn () => $this->clientQuery()
                ->whereIn('product.id', $productIds)
                ->orderByRaw('FIELD(product.id, '.implode(',', $productIds).')')
                ->get(),
            getCoreConfig('time.cache')
        );
    }

    protected function clientQuery(): Builder
    {
        return $this->clientScope($this->model->newQuery())
            ->select('product.*')
            ->with($this->clientRelations());
    }

    protected function clientScope(Builder $query): Builder
    {
        return $query->dateAvailable();
    }

    protected function clientRelations(): array
    {
        return [
            'description',
            'manufacturer',
            'stockStatus',
            'productCategories.category.description',
            'productSpecial',
        ];
    }

    protected function latestCacheKey(int $limit): string
    {
        return implode('_', [getCoreConfig('cache.product_latest'), getUserGroupId(), getUserType(), $limit]).'_';
    }

    protected function specialLatestCacheKey(int $limit): string
    {
        return implode('_', ['product_special_latest', getUserGroupId(), getUserType(), $limit]).'_';
    }

    private static function normalizePrice(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = preg_replace('/[^\d]/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }
}
