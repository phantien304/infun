<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface FilterRepositoryInterface extends BaseRepositoryInterface {

    public function listWithValues(): \Illuminate\Support\Collection;
}
