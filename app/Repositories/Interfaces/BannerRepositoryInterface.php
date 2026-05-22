<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;;

interface BannerRepositoryInterface extends BaseRepositoryInterface
{
    public function getBannerByPage($page, $position, $limit = 3): Collection;
}
