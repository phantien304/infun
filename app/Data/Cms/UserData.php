<?php

namespace App\Data\Cms;

use App\Models\Entities\User;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO cho màn User CMS (Phase 2.3) — CHỈ admin (type=1). KHÔNG BAO GIỜ chứa
 * field `password` (model có $hidden nhưng DTO tự map tay nên vẫn phải cố ý
 * loại trừ — xem docs/ROLE-PERMISSION-PLAN.md Phase 2.3).
 */
class UserData extends Data
{
    public function __construct(
        public int $id,
        public ?string $username,
        public string $email,
        public string $full_name,
        public ?string $avatar,
        public int $status,
        public ?string $deleted_at,
        public Collection $roles,
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: (int) $user->id,
            username: $user->username,
            email: $user->email,
            full_name: $user->full_name,
            avatar: $user->avatar,
            status: (int) $user->status,
            deleted_at: $user->deleted_at?->toDateTimeString(),
            roles: $user->relationLoaded('roles')
                ? $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values()
                : collect(),
        );
    }
}
