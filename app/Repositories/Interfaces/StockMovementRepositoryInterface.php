<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\StockMovement;
use App\Repositories\Base\BaseRepositoryInterface;

interface StockMovementRepositoryInterface extends BaseRepositoryInterface
{
    public function create(array $data): StockMovement;
}
