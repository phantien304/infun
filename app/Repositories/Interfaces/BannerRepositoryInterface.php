<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Banner;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

interface BannerRepositoryInterface extends BaseRepositoryInterface
{
    public function getBannerByPage($page, $position, $limit = 3, ?string $theme = null): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Banner;

    public function saveFromCms(?Banner $banner, array $data): Banner;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Banner;
}
