<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface UserGroupRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithDescription(): Collection;
}
