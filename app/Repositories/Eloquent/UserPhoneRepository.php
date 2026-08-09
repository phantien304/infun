<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserPhone;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserPhoneRepositoryInterface;

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
