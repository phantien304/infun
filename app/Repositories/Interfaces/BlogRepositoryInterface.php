<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Blog;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface BlogRepositoryInterface extends BaseRepositoryInterface
{
    public function getBlogLatest(int $limit = 5);
    public function getDetail($id);

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Blog;

    public function saveFromCms(?Blog $blog, array $data): Blog;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Blog;
}
