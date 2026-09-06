<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\StockStatus;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StockStatusRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithDescription(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?StockStatus;

    public function saveFromCms(?StockStatus $status, array $data): StockStatus;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?StockStatus;
}
