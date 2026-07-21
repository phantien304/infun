<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Manufacturer;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface ManufacturerRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function getManufacturerDetail(int $id): ?Manufacturer;

    public function getAll(): Collection;
}
