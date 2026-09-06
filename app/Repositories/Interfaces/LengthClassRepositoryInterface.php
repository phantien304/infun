<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\LengthClass;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LengthClassRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function flushCache(): void;

    public function getAll(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?LengthClass;

    public function saveFromCms(?LengthClass $lengthClass, array $data): LengthClass;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?LengthClass;
}
