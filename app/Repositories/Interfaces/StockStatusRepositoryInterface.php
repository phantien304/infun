<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface StockStatusRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithDescription(): Collection;
}
