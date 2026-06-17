<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserResetPassword;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserResetPasswordRepositoryInterface;

/**
 * Rewrite stub legacy cùng tên (namespace `App\Repositories\Client\InfunStudio`,
 * extends `BaseInfunStudioRepository` không tồn tại + validator legacy đã xoá).
 *
 * Lưu token reset password tạm thời ở bảng `user_reset_password`
 * (email + code). Token được tạo lúc forgot-password, đối chiếu lúc
 * change-password. KHÔNG cache (1 row/email, lifecycle ngắn).
 */
class UserResetPasswordRepository extends QueryableRepository implements UserResetPasswordRepositoryInterface
{
    public function model(): string
    {
        return UserResetPassword::class;
    }

    public function findByEmail(string $email): ?UserResetPassword
    {
        return $this->resetModel()->where('email', $email)->first();
    }

    /**
     * Tạo mới hoặc cập nhật token cho email — 1 email chỉ giữ 1 token mới
     * nhất (firstOrNew theo email). Trả model đã persist.
     */
    public function upsertForEmail(string $email, string $code): UserResetPassword
    {
        $row = $this->resetModel()->where('email', $email)->firstOrNew();
        $row->fill(['email' => $email, 'code' => $code])->save();

        return $row;
    }

    /** Token hợp lệ khi tồn tại row email + khớp code. */
    public function isValidCode(string $email, string $code): bool
    {
        $row = $this->findByEmail($email);

        return $row !== null && (string) $row->code === (string) $code;
    }

    /** Dọn token sau khi đổi password thành công (dùng 1 lần). */
    public function deleteForEmail(string $email): void
    {
        $this->resetModel()->where('email', $email)->delete();
    }
}
