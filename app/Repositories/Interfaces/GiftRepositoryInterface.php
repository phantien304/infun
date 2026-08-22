<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Gift;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface GiftRepositoryInterface extends BaseRepositoryInterface
{
    public function listActive(): Collection;

    public function findActiveById(int $giftId): ?Gift;

    public function incrementUsedCount(int $giftId, int $by = 1): int;

    public function decrementUsedCount(int $giftId): void;

    public function flushCache(): void;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Gift;

    public function saveFromCms(?Gift $gift, array $data): Gift;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Gift;
}
