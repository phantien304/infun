<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Category;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function getCategoryDetail(int $id): ?Category;
}
