<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface ReviewTagRepositoryInterface extends BaseRepositoryInterface
{
    public function idsByCodes(array $codes): Collection;

    public function insertPivots(array $rows): void;

    public function incrementUsage(array $tagIds): void;
}
