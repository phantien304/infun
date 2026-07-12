<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface LengthClassRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function flushCache(): void;

    public function getAll(): \Illuminate\Support\Collection;
}
