<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewCriteria;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewCriteriaRepositoryInterface;
use Illuminate\Support\Collection;

class ReviewCriteriaRepository extends QueryableRepository implements ReviewCriteriaRepositoryInterface
{
    public function model(): string
    {
        return ReviewCriteria::class;
    }

    public function idsByCodes(array $codes): Collection
    {
        return $this->resetModel()->active()
            ->whereIn('code', $codes)
            ->pluck('id', 'code');
    }
}
