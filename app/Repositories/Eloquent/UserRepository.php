<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\User;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserRepositoryInterface;

/**
 * Repository mới — thay class cùng tên thuộc namespace
 * `App\Repositories\Client\InfunStudio` extend `BaseInfunStudioRepository`
 * (class legacy không còn tồn tại). Chỉ phục vụ luồng frontend account
 * (edit profile, password, newsletter). Auth flow vẫn dùng helper trực
 * tiếp qua Eloquent — không qua repo này.
 */
class UserRepository extends QueryableRepository implements UserRepositoryInterface
{
    public function model(): string
    {
        return User::class;
    }

    public function findById(int $id): ?User
    {
        return $this->resetModel()->where('id', $id)->first();
    }

    public function getProfile(int $id): ?User
    {
        return $this->resetModel()
            ->where('id', $id)
            ->with('userPhone')
            ->first();
    }

    public function updateProfile(int $id, array $data): ?User
    {
        $user = $this->resetModel()->where('id', $id)->first();
        if (! $user) {
            return null;
        }
        $user->fill($data)->save();

        return $user;
    }
}
