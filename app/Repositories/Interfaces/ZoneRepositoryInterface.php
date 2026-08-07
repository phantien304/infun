<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface ZoneRepositoryInterface extends BaseRepositoryInterface
{
    public function nameById(int $id): string;

    public function listAllCached(): Collection;
}
