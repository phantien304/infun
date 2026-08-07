<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Role;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Đọc CHỈ ĐỌC cho Role (spatie). Ghi (create/update kèm sync quyền + luật
 * chống leo thang/tự khoá) nằm ở App\Services\Cms\RoleWriteService — KHÔNG
 * đặt ở đây, xem docs/ROLE-PERMISSION-PLAN.md Phase 2.2.
 */
interface RoleRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Role;
}
