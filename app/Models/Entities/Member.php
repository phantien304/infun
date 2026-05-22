<?php

namespace App\Models\Entities;

use App\Models\Base\Auth\User as CmsUser;
use Laravel\Sanctum\HasApiTokens;

class Member extends CmsUser
{
    use HasApiTokens;
    protected $table = 'members';
    protected $hidden = ['password'];
    protected $primaryKeyAutoIncrement = 'id';
    protected $casts = ['email_verified_at' => 'datetime'];
}
