<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\District;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DistrictRepositoryInterface extends BaseRepositoryInterface
{
    public function listByZone(int $zoneId): Collection;

    public function nameById(int $id): string;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?District;

    public function saveFromCms(?District $district, array $data): District;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?District;
}
