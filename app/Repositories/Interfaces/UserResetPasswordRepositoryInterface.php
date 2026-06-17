<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\UserResetPassword;
use App\Repositories\Base\BaseRepositoryInterface;

interface UserResetPasswordRepositoryInterface extends BaseRepositoryInterface
{
    /** Lấy token reset hiện tại của email (null nếu chưa có). */
    public function findByEmail(string $email): ?UserResetPassword;

    /** Tạo / cập nhật token reset cho email. Trả model đã persist. */
    public function upsertForEmail(string $email, string $code): UserResetPassword;

    /** Kiểm tra token (email + code) còn hợp lệ. */
    public function isValidCode(string $email, string $code): bool;

    /** Xoá token sau khi đổi password thành công (token dùng 1 lần). */
    public function deleteForEmail(string $email): void;
}
