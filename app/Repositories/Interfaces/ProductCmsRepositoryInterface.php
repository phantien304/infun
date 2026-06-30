<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Read CMS (admin) cho Product — TÁCH khỏi ProductRepository storefront vì
 * 2 audience phân kỳ: no-cache vs cache, đa-ngôn-ngữ vs forLocale,
 * withTrashed vs active. Cache invalidation vẫn ở ProductRepository (storefront)
 * qua $cacheMap; repo này KHÔNG cache.
 */
interface ProductCmsRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Product;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Product;
}
