<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReviewRepositoryInterface extends BaseRepositoryInterface
{
    public function listForProduct(int $productId, ?Request $request = null): LengthAwarePaginator;
    public function getActiveCriteria(): Collection;
    public function getActiveTags(int $limit = 8): Collection;
    public function getCriteriaAverages(int $productId): array;
    public function findVerifiedOrderId(int $userId, int $productId): ?int;
    public function hasReviewedFromOrder(int $userId, int $productId): bool;
    public function forgetProductCache(int $productId): void;
    public function sortMenu(): array;
    public function perPageOptions(): array;
}
