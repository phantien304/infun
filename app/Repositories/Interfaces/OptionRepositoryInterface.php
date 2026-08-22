<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Option;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface OptionRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithValues(): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Option;

    public function saveFromCms(?Option $option, array $data): Option;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Option;
}
