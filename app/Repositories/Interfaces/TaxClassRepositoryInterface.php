<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\TaxClass;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaxClassRepositoryInterface extends BaseRepositoryInterface
{
    public function getAll(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?TaxClass;

    public function saveFromCms(?TaxClass $taxClass, array $data): TaxClass;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?TaxClass;
}
