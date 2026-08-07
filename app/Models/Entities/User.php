<?php

namespace App\Models\Entities;

use App\Models\Base\Auth\User as CmsUser;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends CmsUser
{
    use HasApiTokens;
    use SoftDeletes;
    use HasRoles;
    protected $table = 'user';
    protected $hidden = ['password', 'confirm_code'];
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $casts = ['email_verified_at' => 'datetime'];

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'avatar',
        'status',
        'type',
        'address',
        'sex',
        'newsletter',
        'user_group_id',
        'confirm_code',
        'confirmed',
        'social_id',
        'type_register',
        'remember_token',
        'deleted_at',
    ];
    protected static array $destroyRelations = ['userAddress', 'userPhones', 'userRewards', 'userWishlists'];

    public function userAddress()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }

    public function userPhone()
    {
        return $this->belongsTo(UserPhone::class, 'id', 'user_id');
    }

    public function userPhones()
    {
        return $this->hasMany(UserPhone::class, 'user_id', 'id');
    }

    public function userRewards()
    {
        return $this->hasMany(UserReward::class, 'user_id', 'id');
    }

    public function userWishlists()
    {
        return $this->hasMany(UserWishlist::class, 'user_id', 'id');
    }

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id', 'id');
    }
}
