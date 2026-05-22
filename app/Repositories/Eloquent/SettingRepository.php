<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Setting;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\SettingRepositoryInterface;

class SettingRepository extends QueryableRepository implements SettingRepositoryInterface
{
    public function model(): string
    {
        return Setting::class;
    }
}
