<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface TaxClassRepositoryInterface extends BaseRepositoryInterface
{
    public function getAll(): Collection;
}
