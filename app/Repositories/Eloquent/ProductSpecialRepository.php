<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ProductSpecial;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ProductSpecialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ProductSpecialRepository extends QueryableRepository implements ProductSpecialRepositoryInterface
{
    public function model(): string
    {
        return ProductSpecial::class;
    }

    public function getForHome(int $limit = 8): Collection
    {
        return $this->buildQueryCommon(
            $this->model->newQuery()
                ->selectRaw('DISTINCT product_special.product_id, product.sort_order, product.created_at')
        )
            ->orderBy('product.sort_order', 'DESC')
            ->orderBy('product.created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function getListForWeb(array $params = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $this->processParams($params);

        $query = $this->buildQueryCommon(
            $this->model->newQuery()
                ->selectRaw('DISTINCT product_special.product_id')
        );

        if (request()->has('product_filter')) {
            $query->leftJoin('product_filter', 'product_filter.product_id', '=', 'product.id');
        }

        $query->groupBy('product_special.product_id');

        $this->applySearchParams($query, $params);

        $sortField = Arr::get($params, 'sort_field', 'product.sort_order');
        $sortType  = Arr::get($params, 'sort_type', 'DESC');
        $query->orderBy($sortField, $sortType)
            ->orderBy('product.created_at', 'DESC');

        $perPage = Arr::get($params, 'per_page', 20);

        return $paginate
            ? $query->paginate($perPage)->appends($params)
            : $query->get();
    }

    protected function processParams(array &$params): void
    {
        $productSpecial = data_get($params, 'product_special', []);

        if (filled(data_get($productSpecial, 'price_gteq'))) {
            $params['product_special']['price_gteq'] = preg_replace('/,|\.|đ/', '', data_get($productSpecial, 'price_gteq'));
        }

        if (filled(data_get($productSpecial, 'price_lteq'))) {
            $params['product_special']['price_lteq'] = preg_replace('/,|\.|đ/', '', data_get($productSpecial, 'price_lteq'));
        }

        if (filled(data_get($params, 'product.quantity_gt')) && filled(data_get($params, 'product.quantity_lteq_or_quantity_isnull'))) {
            unset($params['product']);
        }

        if (Arr::has($params, 'sort_field')) {
            $tableColumns = $this->model->getTableColumnAndTypeList();

            if (isset($tableColumns[$params['sort_field']])) {
                $params['sort_field'] = 'product_special.' . $params['sort_field'];
            } else {
                unset($params['sort_field']);
            }
        }
    }

    protected function buildQueryCommon($query)
    {
        $now = Carbon::now();

        return $query
            ->leftJoin('product', 'product.id', '=', 'product_special.product_id')
            ->leftJoin('product_description', 'product_description.product_id', '=', 'product.id')
            ->dateAvailable()
            ->whereNull('product.deleted_at')
            ->forLocale('product_description')
            ->where('product_special.user_group_id', getUserGroupId())
            ->where(function ($q) use ($now) {
                $q->where('product_special.date_start', '<=', $now)
                    ->orWhereNull('product_special.date_start');
            })
            ->where(function ($q) use ($now) {
                $q->where('product_special.date_end', '>', $now)
                    ->orWhereNull('product_special.date_end');
            });
    }

    protected function applySearchParams($query, array $params): void
    {
        if ($priceFrom = Arr::get($params, 'product_special.price_gteq')) {
            $query->where('product_special.price', '>=', $priceFrom);
        }

        if ($priceTo = Arr::get($params, 'product_special.price_lteq')) {
            $query->where('product_special.price', '<=', $priceTo);
        }

        if ($qtyGt = Arr::get($params, 'product.quantity_gt')) {
            $query->where('product.quantity', '>', $qtyGt);
        }
    }
}
