<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class RoleUser extends Base
{
    protected $table = 'role_user';
    public $primaryKey = ['role_id', 'user_id'];
    public $incrementing = false;

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
}
