<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\User;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Repository mới — thay class cùng tên thuộc namespace
 * `App\Repositories\Client\InfunStudio` extend `BaseInfunStudioRepository`
 * (class legacy không còn tồn tại). Phục vụ luồng frontend account
 * (edit profile, password, newsletter) LẪN auth (register / social /
 * verify email / reset password) — gọi qua AuthService thay vì controller
 * tự query Eloquent như code legacy.
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

    public function findByEmail(string $email): ?User
    {
        return $this->resetModel()->where('email', $email)->first();
    }

    public function findMemberByEmail(string $email): ?User
    {
        return $this->resetModel()
            ->where('email', $email)
            ->where('type', (int) getCoreConfig('user.type.member'))
            ->first();
    }

    public function findByConfirmCode(string $code, string $email): ?User
    {
        return $this->resetModel()
            ->where('confirm_code', $code)
            ->where('email', $email)
            ->first();
    }

    public function createUser(array $data): User
    {
        return $this->resetModel()->create($data);
    }

    public function markConfirmed(User $user): User
    {
        $user->fill(['confirmed' => 1])->save();

        return $user;
    }

    /**
     * Đặt lại password theo email (luồng reset qua token). Lock row chống
     * race khi user mở nhiều tab. Trả null nếu không tìm thấy user.
     */
    public function updatePasswordByEmail(string $email, string $plainPassword): ?User
    {
        $user = $this->resetModel()->where('email', $email)->lockForUpdate()->first();
        if (! $user) {
            return null;
        }
        $user->fill(['password' => Hash::make($plainPassword)])->save();

        return $user;
    }

    public function updatePasswordById(int $id, string $plainPassword): ?User
    {
        $user = $this->resetModel()->where('id', $id)->lockForUpdate()->first();
        if (! $user) {
            return null;
        }
        $user->fill(['password' => Hash::make($plainPassword)])->save();

        return $user;
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
