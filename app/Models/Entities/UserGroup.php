<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Base
{
    use SoftDeletes;
    protected $table = 'user_group';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['productRewards', 'taxRateToUserGroups'];

    public function descriptions()
    {
        return $this->hasMany(UserGroupDescription::class, 'user_group_id', 'id');
    }

    public function productRewards()
    {
        return $this->hasMany(ProductReward::class, 'user_group_id', 'id');
    }

    public function productDiscounts()
    {
        return $this->hasMany(ProductDiscount::class, 'user_group_id', 'id');
    }

    public function taxRateToUserGroups()
    {
        return $this->hasMany(TaxRateToUserGroup::class, 'user_group_id', 'id');
    }
}
