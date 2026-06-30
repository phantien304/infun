<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Product;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ProductCmsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ProductCmsRepository extends QueryableRepository implements ProductCmsRepositoryInterface
{
    public function model(): string
    {
        return Product::class;
    }

    protected function cmsDetailRelations(): array
    {
        return [
            'descriptions',
            'productCategories.category.description',
            'productFilters',
            'productRelated.product.description',
            'productIngredients.ingredient.description',
            'productAttributes',
            'productImages',
            'productRewards',
            'productDiscounts',
            'productOptions.option',
            'productVariants.productVariantAttributes',
            'productVariants.productStocks',
            'defaultVariant',
        ];
    }

    /** List CMS: join name theo ngôn ngữ, search/sort/soft-delete, list NHẸ. */
    public function listForCms(Request $request): LengthAwarePaginator
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        $lang    = $request->input('language_code') ?: $defaultLang;
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $sortMap = [
            'id'                       => 'product.id',
            'model'                    => 'product.model',
            'badge'                    => 'product.badge',
            'quantity'                 => 'product.quantity',
            'name'                     => 'product_description.name',
            'product_description.name' => 'product_description.name',
        ];
        $sortColumn = $sortMap[$request->input('sort', 'id')] ?? 'product.id';

        $query = Product::query()
            ->leftJoin('product_description', function ($join) use ($lang) {
                $join->on('product_description.product_id', '=', 'product.id')
                    ->where('product_description.language_code', '=', $lang);
            })
            ->select('product.*', 'product_description.name')
            ->with(['defaultVariant', 'productDraft']);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('product_description.name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sortColumn, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Product
    {
        return Product::withTrashed()->with($this->cmsDetailRelations())->find($id);
    }

    public function deleteByIds(array $ids): int
    {
        return Product::whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return Product::withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Product
    {
        $product = Product::withTrashed()->find($id);
        $product?->restore();

        return $product;
    }
}
