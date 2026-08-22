<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Manufacturer;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ManufacturerRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function getManufacturerDetail(int $id): ?Manufacturer;

    public function getAll(): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Manufacturer;

    public function saveFromCms(?Manufacturer $manufacturer, array $data): Manufacturer;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Manufacturer;
}
