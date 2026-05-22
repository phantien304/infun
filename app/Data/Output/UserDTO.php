<?php

namespace App\Data\Output;

use App\Models\Entities\User;
use Spatie\LaravelData\Data;

class UserDTO extends Data
{
    public function __construct(
        public int $id,
        public string $full_name,
        public string $address,
        public string $avatar,
    ) {}
    public static function fromModel(User $user): self
    {
        return new self(
            id: (int) $user->id,
            full_name: (string) ($user->full_name ?? ''),
            address: (string) ($user->address ?? ''),
            avatar: (string) ($user->avatar ?? ''),
        );
    }
}
