<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\GeoZone;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GeoZoneRepositoryInterface extends BaseRepositoryInterface
{
    public function getAll(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?GeoZone;

    public function saveFromCms(?GeoZone $geoZone, array $data): GeoZone;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?GeoZone;
}
