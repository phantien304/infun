<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class PermissionRole extends Base
{
    protected $table = 'permission_role';
    public $guarded = [];
    public $primaryKey = ['permission_id', 'role_id'];
    public $incrementing = false;
    public $timestamps = false;
}
