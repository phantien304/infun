<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StockStatus;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\StockStatusRepositoryInterface;
use Illuminate\Support\Collection;

class StockStatusRepository extends QueryableRepository implements StockStatusRepositoryInterface
{
    public function model(): string
    {
        return StockStatus::class;
    }

    public function listWithDescription(): Collection
    {
        return StockStatus::with('description')->get();
    }
}
