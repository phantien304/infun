<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Information;
use App\Repositories\Base\BaseRepositoryInterface;

interface InformationRepositoryInterface extends BaseRepositoryInterface
{
    public function getDetail($id): ?Information;
}
