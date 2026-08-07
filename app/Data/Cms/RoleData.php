<?php

namespace App\Data\Cms;

use App\Models\Entities\Role;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class RoleData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $created_at,
        public ?int $permissions_count,
        public ?int $users_count,
        public Collection $permission_ids,
    ) {
    }

    public static function fromModel(Role $role): self
    {
        return new self(
            id: (int) $role->id,
            name: $role->name,
            created_at: $role->created_at?->toDateTimeString(),
            permissions_count: $role->permissions_count ?? null,
            users_count: $role->users_count ?? null,
            permission_ids: $role->relationLoaded('permissions')
                ? $role->permissions->pluck('id')
                : collect(),
        );
    }
}
