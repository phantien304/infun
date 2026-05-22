<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StoreReview;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;
use Override;

class StoreReviewRepository extends QueryableRepository implements StoreReviewRepositoryInterface
{
    public function model(): string
    {
        return StoreReview::class;
    }
    public function getById(int $id)
    {
        return $this->model
            ->with($this->withRelations())
            ->find($id);
    }
    public function getStoreReviewsFeatured(int $limit = 20)
    {
        return $this->resetModel()
            ->with($this->withRelations())
            ->where('featured', 1)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();
    }

    protected function withRelations(): array
    {
        return [
            'description',
            'user'
        ];
    }
}
