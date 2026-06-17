<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\User;
use App\Repositories\Base\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(int $id): ?User;

    /** Tìm user bất kỳ theo email (dùng cho social login lookup). */
    public function findByEmail(string $email): ?User;

    /** Tìm user là KHÁCH HÀNG (type = member) theo email — forgot password. */
    public function findMemberByEmail(string $email): ?User;

    /** Tìm user theo cặp (confirm_code, email) — verify email. */
    public function findByConfirmCode(string $code, string $email): ?User;

    /** Tạo user mới (register / social). Trả model đã persist. */
    public function createUser(array $data): User;

    /** Đánh dấu user đã xác thực email (confirmed = 1). */
    public function markConfirmed(User $user): User;

    /**
     * Đặt lại password theo email (luồng reset qua token), lock row chống
     * race. Trả null nếu không tìm thấy user.
     */
    public function updatePasswordByEmail(string $email, string $plainPassword): ?User;

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
