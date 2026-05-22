<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class Permission extends Base
{
    protected $table = 'permissions';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'permission_role',
            'permission_id',
            'role_id'
        );
    }
}
