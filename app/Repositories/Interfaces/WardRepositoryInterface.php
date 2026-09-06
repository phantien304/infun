<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Ward;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface WardRepositoryInterface extends BaseRepositoryInterface
{
    public function listByDistrict(int $districtId): Collection;

    public function nameById(int $id): string;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Ward;

    public function saveFromCms(?Ward $ward, array $data): Ward;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Ward;
}
