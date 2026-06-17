<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Base
{
    use SoftDeletes;
    protected $table = 'roles';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['permissionRoles', 'roleUsers'];

    public function permissionRoles()
    {
        return $this->hasMany(PermissionRole::class, 'role_id', 'id');
    }

    public function roleUsers()
    {
        return $this->hasMany(RoleUser::class, 'role_id', 'id');
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'role_id',
            'permission_id'
        );
    }
}
