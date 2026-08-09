<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserResetPassword;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserResetPasswordRepositoryInterface;

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

    public function upsertForEmail(string $email, string $code): UserResetPassword
    {
        $row = $this->resetModel()->where('email', $email)->firstOrNew();
        $row->fill(['email' => $email, 'code' => $code])->save();

        return $row;
    }

    public function isValidCode(string $email, string $code): bool
    {
        $row = $this->findByEmail($email);

        return $row !== null && (string) $row->code === (string) $code;
    }

    public function deleteForEmail(string $email): void
    {
        $this->resetModel()->where('email', $email)->delete();
    }
}
