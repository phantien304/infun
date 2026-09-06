<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\TaxRate;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaxRateRepositoryInterface extends BaseRepositoryInterface
{
    public function getAll(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?TaxRate;

    public function saveFromCms(?TaxRate $taxRate, array $data): TaxRate;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?TaxRate;
}
