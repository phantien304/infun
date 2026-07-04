<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface CurrencyRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;
}
