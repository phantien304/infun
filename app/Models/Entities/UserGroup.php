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
    protected $fillable = [
        'approval',
        'sort_order',
        'deleted_at',
    ];

    public function descriptions()
    {
        return $this->hasMany(UserGroupDescription::class, 'user_group_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(UserGroupDescription::class, 'user_group_id', 'id')->forLocale();
    }

    public function productRewards()
    {
        return $this->hasMany(ProductReward::class, 'user_group_id', 'id');
    }

    public function productVariantDiscounts()
    {
        return $this->hasMany(ProductVariantDiscount::class, 'user_group_id', 'id');
    }

    public function taxRateToUserGroups()
    {
        return $this->hasMany(TaxRateToUserGroup::class, 'user_group_id', 'id');
    }
}
