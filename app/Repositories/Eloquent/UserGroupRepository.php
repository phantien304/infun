<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserGroup;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserGroupRepositoryInterface;
use Illuminate\Support\Collection;

class UserGroupRepository extends QueryableRepository implements UserGroupRepositoryInterface
{
    public function model(): string
    {
        return UserGroup::class;
    }

    public function listWithDescription(): Collection
    {
        return UserGroup::with('description')->get();
    }
}
