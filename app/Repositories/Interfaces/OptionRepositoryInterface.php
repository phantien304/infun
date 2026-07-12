<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface OptionRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithValues(): Collection;
}
