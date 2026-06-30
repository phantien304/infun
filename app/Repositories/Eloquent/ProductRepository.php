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

            AllowedFilter::callback('keyword', function (Builder $q, $value) {
                if (! is_string($value)) {
                    return;
                }
                $value = trim($value);
                if ($value === '') {
                    return;
                }
                $like = '%'.$value.'%';
                $q->where(function (Builder $qq) use ($like) {
                    $qq->where('product_description.name', 'like', $like)
                        ->orWhere('product.sku', 'like', $like)
                        ->orWhere('product.model', 'like', $like);
                });
            }),
        ];
    }

    protected function sortMap(): array
    {
        return [
            'created_at' => [
                'db'    => AllowedSort::field('created_at', 'product.created_at'),
                'meili' => 'created_at',
                'menu'  => true,
            ],
            'price' => [
                'db'    => AllowedSort::callback(
                    'price',
                    fn (Builder $q, bool $descending) => $q->orderByEffectivePrice($descending ? 'desc' : 'asc')
                ),
                'meili' => 'min_variant_price',
                'menu'  => true,
            ],
            'name' => [
                'db'    => AllowedSort::field('name', 'product_description.name'),
                'meili' => null,
                'menu'  => true,
            ],
            'viewed' => [
                'db'    => AllowedSort::field('viewed', 'product.viewed'),
                'meili' => 'viewed',
                'menu'  => true,
            ],
            'rating_avg' => [
                'db'    => AllowedSort::field('rating_avg', 'product.rating_avg'),
                'meili' => 'rating_avg',
                'menu'  => true,
            ],
        ];
    }

    protected function allowedSorts(): array
    {
        return array_values(array_map(fn (array $row) => $row['db'], $this->sortMap()));
    }

    protected function defaultSort(): string
    {
        return '-product.created_at';
    }

    protected function sortMenu(): array
    {
        $menu = [];
        foreach ($this->sortMap() as $token => $row) {
            if (! ($row['menu'] ?? false)) {
                continue;
            }
            $menu[] = '-'.$token;
            $menu[] = $token;
        }

        return $menu;
    }

    protected function baseQuery(): Builder
    {
        $query = $this->model->newQuery()->select('product.*');

        $sort    = ltrim((string) request()->input('sort', ''), '-');
        $keyword = trim((string) request()->input('filter.keyword', ''));
        if ($sort === 'name' || $keyword !== '') {
            $query->leftJoin('product_description', function ($join) {
                $join->on('product_description.product_id', '=', 'product.id')
                    ->where('product_description.language_code', app()->getLocale());
            });
        }

        return $query;
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

    public function list(?Request $request = null, ?int $perPage = null, ?\Closure $modifyBase = null): LengthAwarePaginator
    {
        $request ??= request();
        $keyword = trim((string) $request->input('filter.keyword', ''));

        if ($keyword === '' || config('scout.driver') !== 'meilisearch') {
            return parent::list($request, $perPage, $modifyBase);
        }

        $perPage ??= (int) $request->get('per_page', $this->defaultPerPage);
        $perPage = max(1, min($perPage, $this->maxPerPage));

        try {
            return $this->searchViaMeilisearch($request, $keyword, $perPage, $modifyBase)
                ->appends($request->query());
        } catch (\Throwable $e) {
            logError('[ProductRepository::list] Meilisearch failed, fallback DB', [
                'keyword'   => $keyword,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);

            return parent::list($request, $perPage, $modifyBase);
        }
    }

    protected function searchViaMeilisearch(
        Request $request,
        string $keyword,
        int $perPage,
        ?\Closure $modifyBase = null
    ): LengthAwarePaginator {
        $builder = Product::search($keyword);

        $manufacturer = self::positiveIntList($request->input('filter.manufacturer_id', []));
        if (! empty($manufacturer)) {
            $builder->whereIn('manufacturer_id', $manufacturer);
        }

        $min = self::normalizePrice($request->input('filter.price_min'));
        $max = self::normalizePrice($request->input('filter.price_max'));
        if ($min !== null) {
            $builder->where('max_variant_price', '>=', $min);
        }
        if ($max !== null) {
            $builder->where('min_variant_price', '<=', $max);
        }

        $sort = (string) $request->input('sort', '');
        if ($sort !== '') {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $token     = ltrim($sort, '-');
            $meiliAttr = $this->sortMap()[$token]['meili'] ?? null;
            if ($meiliAttr !== null) {
                $builder->orderBy($meiliAttr, $direction);
            }
        }

        $cardRelations    = $this->cardRelations();
        $selectedFilters  = self::positiveIntList($request->input('filter.filter_value_id', []));
        $selectedCategory = self::positiveIntList($request->input('filter.category_id', []));
        $inStockRaw = collect((array) $request->input('filter.in_stock', []))
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v === '0' || $v === '1')
            ->unique()
            ->values();

        $builder->query(function (Builder $qb) use (
            $cardRelations,
            $selectedFilters,
            $selectedCategory,
            $inStockRaw,
            $modifyBase,
        ) {
            $qb->select('product.*')
                ->dateAvailable()
                ->with($cardRelations);

            if (! empty($selectedCategory)) {
                $qb->whereHas('productCategories', fn ($qq) => $qq->whereIn('category_id', $selectedCategory));
            }

            if (! empty($selectedFilters)) {
                $qb->whereHas('productFilters', fn ($qq) => $qq->whereIn('filter_value_id', $selectedFilters))
                    ->with([
                        'productFilters' => fn ($q) => $q->whereIn('filter_value_id', $selectedFilters),
                        'productFilters.filterValue.description',
                    ]);
            }

            if ($inStockRaw->count() === 1) {
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
                $inStockRaw->first() === '1'
                    ? $qb->whereExists($inStockExists)
                    : $qb->whereNotExists($inStockExists);
            }

            if ($modifyBase !== null) {
                $modifyBase($qb);
            }
        });

        return $builder->paginate($perPage);
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

    private static function positiveIntList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $value),
            fn (int $v) => $v > 0
        ));
    }
}
