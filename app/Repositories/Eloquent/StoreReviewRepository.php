<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StoreReview;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class StoreReviewRepository extends QueryableRepository implements StoreReviewRepositoryInterface
{
    public function model(): string
    {
        return StoreReview::class;
    }
    public function getById(int $id)
    {
        return $this->model->with('description')->find($id);
    }
    public function getStoreReviews()
    {
        return $this->model
            ->orderBy('id', 'DESC')
            ->where('featured', 1)
            ->limit(20)
            ->get();
    }

    public function getListForWeb(array $params = [], bool $paginate = true)
    {
        $query = $this->model->with([
            'description',
            'user'
        ]);

        if ($paginate) {
            return $query->orderBy('id', 'DESC')->paginate($params['per_page'] ?? 10)->appends($params);
        }

        return $query->orderBy('id', 'DESC')->get();
    }
}
