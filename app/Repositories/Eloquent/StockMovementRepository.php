<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StockMovement;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;

class StockMovementRepository extends QueryableRepository implements StockMovementRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return StockMovement::class;
    }

    public function create(array $data): StockMovement
    {
        return $this->resetModel()->create($data);
    }
}
