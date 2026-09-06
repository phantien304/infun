<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\WeightClass;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface WeightClassRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function flushCache(): void;

    public function getAll(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?WeightClass;

    public function saveFromCms(?WeightClass $weightClass, array $data): WeightClass;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?WeightClass;
}
