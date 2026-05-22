<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface ManufacturerRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;
}
