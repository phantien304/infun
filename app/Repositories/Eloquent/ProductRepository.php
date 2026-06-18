<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Product;
use App\Models\Entities\ProductRelated;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

                $backorder = (int) getCoreConfig('stock.policy.backorder');
                $untracked = (int) getCoreConfig('stock.policy.untracked');
                $inStockExists = function ($qq) use ($backorder, $untracked) {
                    $qq->select(DB::raw(1))
                        ->from('product_variant as pv')
                        ->join('product_stock as ps', 'ps.product_variant_id', '=', 'pv.id')
                        ->whereColumn('pv.product_id', 'product.id')
                        ->whereNull('pv.deleted_at')
                        ->where(function ($w) use ($backorder, $untracked) {
                            $w->whereIn('ps.inventory_policy', [$backorder, $untracked])
                                ->orWhereRaw('(ps.on_hand - ps.reserved) > 0');
                        });
                };

                $values->first() === '1'
                    ? $q->whereExists($inStockExists)
                    : $q->whereNotExists($inStockExists);
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

    protected function beforeBuildForList(Builder $query): Builder
    {
        $query = $this->cardScope($query);

        $min = self::normalizePrice(request()->input('filter.price_min'));
        $max = self::normalizePrice(request()->input('filter.price_max'));
        if ($min !== null || $max !== null) {
            $query->effectivePriceBetween($min, $max);
        }

        return $query;
    }

    protected function withRelations(): array
    {
        $relations = $this->cardRelations();
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

    public function getProductDetail(int $id): ?Product
    {
        if ($id <= 0) {
            return null;
        }

        return $this->rememberCache(
            $this->detailCacheKey($id),
            fn () => $this->resetModel()
                ->with($this->detailRelations())
                ->find($id),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('cache.product_root'), getCoreConfig('cache.products').$id],
        );
    }

    public function findReviewableProduct(int $id): ?Product
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()
            ->where('id', $id)
            ->where('is_review', 1)
            ->first();
    }

    public function findAddableToCart(int $id): ?Product
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()
            ->where('id', $id)
            ->where('is_add_cart', 1)
            ->dateAvailable()
            ->with('description')
            ->first();
    }

    public function incrementViewed(int $id): void
    {
        try {
            DB::table('product')->where('id', $id)->update([
                'viewed' => DB::raw('viewed + 1'),
            ]);
        } catch (\Throwable $e) {
            logError($e->getMessage());
        }
    }

    public function getProductRelatedByProductId(int $id, int $limit = 4)
    {
        $relatedIds = ProductRelated::query()
            ->where('product_id', $id)
            ->where('related_id', '!=', $id)
            ->take($limit)
            ->pluck('related_id')
            ->all();

        return $this->getProductRelated($relatedIds);
    }

    public function getListSpecial(?Request $request = null): LengthAwarePaginator
    {
        return $this->list($request, null, fn (Builder $q) => $q->hasActiveSpecial());
    }

    public function getProductVariantSpecialLatest(int $limit = 8)
    {
        return $this->rememberCache(
            $this->specialLatestCacheKey($limit),
            fn () => $this->cardQuery()
                ->hasActiveSpecial()
                ->orderBy('product.sort_order', 'DESC')
                ->orderBy('product.created_at', 'DESC')
                ->take($limit)
                ->get(),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('cache.product_root')],
        );
    }

    public function getProductFeature(int $limit = 6)
    {
        return $this->cardQuery()
            ->where('product.badge', 'feature')
            ->orderBy('product.id', 'DESC')
            ->take($limit)
            ->get();
    }

    public function getProductLatest(int $limit = 6)
    {
        return $this->rememberCache(
            $this->latestCacheKey($limit),
            fn () => $this->cardQuery()
                ->orderBy('product.created_at', 'DESC')
                ->take($limit)
                ->get(),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('cache.product_root'), getCoreConfig('cache.product_latest')],
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

        return $this->rememberCache(
            $key,
            fn () => $this->cardQuery()
                ->whereIn('product.id', $productIds)
                ->orderByRaw('FIELD(product.id, '.implode(',', $productIds).')')
                ->get(),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('cache.product_root')],
        );
    }

    protected function cardQuery(): Builder
    {
        return $this->cardScope($this->model->newQuery())
            ->select('product.*')
            ->with($this->cardRelations());
    }

    protected function cardScope(Builder $query): Builder
    {
        return $query->dateAvailable();
    }

    protected function cardRelations(): array
    {
        return [
            'description',
            'manufacturer',
            'stockStatus',
            'productCategories.category.description',
            'defaultVariant.productStock',
            'defaultVariant.productVariantSpecial',
        ];
    }

    protected function detailRelations(): array
    {
        return array_merge($this->cardRelations(), [
            'productImages' => fn ($q) => $q
                ->where('is_active', true)
                ->whereIn('type', [
                    setting('product_image.type.main'),
                    setting('product_image.type.gallery'),
                    setting('product_image.type.zoom'),
                ])
                ->orderBy('sort_order'),
            'defaultVariant.productVariantSpecial',
            'weightClass.description',
            'productOptions.option.description',
            'productOptions.option.optionValues.description',
            'productOptions.productOptionValues.optionValue.description',
            'productVariants' => fn ($q) => $q
                ->orderBy('is_default', 'DESC')
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC'),
            'productVariants.productVariantAttributes.optionValue.description',
            'productVariants.productVariantAttributes.option.description',
            'productVariants.productStock',
            'productVariants.description',
            'productVariants.productVariantSpecial',
        ]);
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.product_root')]);
    }

    public function flushProductCache(int $id): void
    {
        $this->forgetCacheTagged([getCoreConfig('cache.products').$id]);
    }

    protected function detailCacheKey(int $id): string
    {
        return implode('_', [getCoreConfig('cache.products').$id, 'detail', getUserGroupId(), getUserType()]).'_';
    }

    protected function latestCacheKey(int $limit): string
    {
        return implode('_', [getCoreConfig('cache.product_latest'), getUserGroupId(), getUserType(), $limit]).'_';
    }

    protected function specialLatestCacheKey(int $limit): string
    {
        return implode('_', [getCoreConfig('cache.product_special_latest'), getUserGroupId(), getUserType(), $limit]).'_';
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
