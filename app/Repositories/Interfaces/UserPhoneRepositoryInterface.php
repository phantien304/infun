<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\UserPhone;
use App\Repositories\Base\BaseRepositoryInterface;

interface UserPhoneRepositoryInterface extends BaseRepositoryInterface
{
    public function findForUser(int $userId): ?UserPhone;

    /**
     * Lưu phone cho user. Bảo toàn cờ `is_verify` nếu record cũ đã verified
     * (vì khi user đổi profile không bắt re-verify, nhưng nếu đổi sang số
     * khác thì caller cần tự reset is_verify trước — repo không tự đoán).
     */
    public function upsertForUser(int $userId, array $data): UserPhone;
}
