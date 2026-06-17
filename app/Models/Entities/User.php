<?php

namespace App\Models\Entities;

use App\Models\Base\Auth\User as CmsUser;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends CmsUser
{
    use HasApiTokens;
    use SoftDeletes;
    protected $table = 'user';
    protected $hidden = ['password', 'confirm_code'];
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $casts = ['email_verified_at' => 'datetime'];
    protected static array $destroyRelations = ['roleUsers2', 'userAddress', 'userPhones', 'userRewards', 'userWishlists'];

    public function roleUser()
    {
        return $this->belongsTo(RoleUser::class, 'id', 'user_id');
    }

    public function roleUsers()
    {
        return $this->belongsToMany(RoleUser::class, 'role_user', 'user_id', 'role_id');
    }

    public function roleUsers2()
    {
        return $this->hasMany(RoleUser::class, 'user_id', 'id');
    }

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

    public function roles()
    {
        return $this->morphToMany(
            Role::class,
            'user',
            'role_user',
            'user_id',
            'role_id'
        );
    }

    public function permissions()
    {
        return $this->morphToMany(
            Permission::class,
            'user',
            'permission_user',
            'user_id',
            'permission_id'
        );
    }
}
