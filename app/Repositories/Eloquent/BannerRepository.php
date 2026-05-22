<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Banner;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;;

class BannerRepository extends QueryableRepository implements BannerRepositoryInterface
{
    public function model(): string
    {
        return Banner::class;
    }

    public function getBannerByPage($page, $position, $limit = 3): Collection
    {
        return $this->model->where('page', 'like',  '%' . $page . '%')
            ->where('position', $position)
            ->with([
                'description',
                'values' => fn($q) => $q->orderBy('sort_order'),
                'values.valueDescription',
            ])
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }
}
