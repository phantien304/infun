<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\User;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findMemberByEmail(string $email): ?User;

    public function findByConfirmCode(string $code, string $email): ?User;

    public function createUser(array $data): User;

    public function markConfirmed(User $user): User;

    public function updatePasswordByEmail(string $email, string $plainPassword): ?User;

    public function updatePasswordById(int $id, string $plainPassword): ?User;

    public function getProfile(int $id): ?User;

    public function updateProfile(int $id, array $data): ?User;

    public function searchForCms(string $keyword = '', ?int $type = null, int $limit = 10): Collection;

    // ----- CMS admin (Phase 2.3, xem docs/ROLE-PERMISSION-PLAN.md — CHỈ
    // quản tài khoản quản trị `type=1`, KHÔNG đụng Member `type=2` (đã có
    // domain Customer/Account riêng, xem AccountService) -----

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getAdminForCms(int $id): ?User;

    /** Luôn ép type=1 (admin) bất kể input — KHÔNG dùng để tạo Member. */
    public function createAdmin(array $data, array $roleIds): User;

    /** password trong $data optional — rỗng/thiếu thì GIỮ NGUYÊN mật khẩu cũ. */
    public function updateAdmin(User $user, array $data, array $roleIds): User;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?User;
}
