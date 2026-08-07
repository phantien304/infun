<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Information;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface InformationRepositoryInterface extends BaseRepositoryInterface
{
    public function getDetail($id): ?Information;

    public function listWithDescription(): Collection;
}
