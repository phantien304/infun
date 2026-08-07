<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\StoreReview;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface StoreReviewRepositoryInterface extends BaseRepositoryInterface
{
    public function getStoreReviewsFeatured(int $limit = 20);

    public function getStoreReviewsByProduct(int $productId, int $limit = 16);

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?StoreReview;

    public function saveFromCms(?StoreReview $storeReview, array $data): StoreReview;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?StoreReview;
}
