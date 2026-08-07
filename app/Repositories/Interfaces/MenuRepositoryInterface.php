<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Menu;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

interface MenuRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Menu> */
    public function getMenuByPosition(string $position = 'top', ?string $theme = null): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Menu;

    public function saveFromCms(?Menu $menu, array $data): Menu;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Menu;
}
