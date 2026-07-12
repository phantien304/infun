<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\User;
use App\Repositories\Base\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findMemberByEmail(string $email): ?User;

    public function findByConfirmCode(string $code, string $email): ?User;

    public function createUser(array $data): User;

    public function markConfirmed(User $user): User;

    public function updatePasswordByEmail(string $email, string $plainPassword): ?User;

    public function updatePasswordById(int $id, string $plainPassword): ?User;

    public function getProfile(int $id): ?User;

    public function updateProfile(int $id, array $data): ?User;
}
