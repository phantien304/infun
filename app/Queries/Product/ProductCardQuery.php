<?php

namespace App\Queries\Product;

use App\Models\Entities\Product;
use App\Queries\Query;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductCardQuery implements Query
{
    public function builder(): Builder
    {
        return Product::query()
            ->dateAvailable()
            ->select('product.*')
            ->with($this->relations());
    }

    public function feature(int $limit): Collection
    {
        return $this->builder()
            ->where('product.badge', 'feature')
            ->orderBy('product.id', 'DESC')
            ->take($limit)
            ->get();
    }

    public function latest(int $limit): Collection
    {
        return $this->builder()
            ->orderBy('product.created_at', 'DESC')
            ->take($limit)
            ->get();
    }

    public function specialLatest(int $limit): Collection
    {
        return $this->builder()
            ->hasActiveSpecial()
            ->orderBy('product.sort_order', 'DESC')
            ->orderBy('product.created_at', 'DESC')
            ->take($limit)
            ->get();
    }

    public function relatedIn(array $productIds): Collection
    {
        if (empty($productIds)) {
            return new Collection();
        }

        return $this->builder()
            ->whereIn('product.id', $productIds)
            ->orderByRaw('FIELD(product.id, '.implode(',', $productIds).')')
            ->get();
    }

    public function relations(): array
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
}
