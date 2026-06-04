<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\User;
use App\Repositories\Base\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(int $id): ?User;

    /**
     * Tải hồ sơ user kèm relations dùng cho trang `account.edit`
     * (userPhone để lấy số điện thoại). KHÔNG eager-load wishlist /
     * address để giữ payload nhẹ.
     */
    public function getProfile(int $id): ?User;

    /**
     * Update partial cột trên `user` qua mass-assignment. Trả model sau khi
     * save để caller có thể tiếp tục mutate (vd attach userPhone).
     */
    public function updateProfile(int $id, array $data): ?User;
}
