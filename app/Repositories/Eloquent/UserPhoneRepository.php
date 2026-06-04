<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserPhone;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserPhoneRepositoryInterface;

/**
 * Replace stub legacy `App\Repositories\Client\InfunStudio\UserPhoneRepository`.
 *
 * Quy ước is_verify: caller (AccountService) PHẢI quyết định reset
 * is_verify hay không. Repository không tự đoán — vì có case admin sửa
 * email/full_name không đụng phone, lúc đó phải giữ verified.
 */
class UserPhoneRepository extends QueryableRepository implements UserPhoneRepositoryInterface
{
    public function model(): string
    {
        return UserPhone::class;
    }

    public function findForUser(int $userId): ?UserPhone
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->first();
    }

    public function upsertForUser(int $userId, array $data): UserPhone
    {
        $phone = $this->resetModel()
            ->where('user_id', $userId)
            ->firstOrNew();
        $phone->fill(array_merge($data, ['user_id' => $userId]))->save();

        return $phone;
    }
}
