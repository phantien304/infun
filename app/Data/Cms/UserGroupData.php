<?php

namespace App\Data\Cms;

use App\Models\Entities\UserGroup;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class UserGroupData extends Data
{
    public function __construct(
        public int $id,
        public int $approval,
        public int $sort_order,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $user_group_descriptions,
    ) {
    }

    public static function fromModel(UserGroup $userGroup): self
    {
        $name = $userGroup->name
            ?? ($userGroup->relationLoaded('descriptions')
                ? $userGroup->descriptions->first()?->name
                : null);

        return new self(
            id: (int) $userGroup->id,
            approval: (int) $userGroup->approval,
            sort_order: (int) $userGroup->sort_order,
            name: $name,
            deleted_at: $userGroup->deleted_at?->toDateTimeString(),
            user_group_descriptions: $userGroup->relationLoaded('descriptions')
                ? UserGroupDescriptionData::collect($userGroup->descriptions, Collection::class)
                : collect(),
        );
    }
}
