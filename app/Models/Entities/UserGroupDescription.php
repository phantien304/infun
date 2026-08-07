<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class UserGroupDescription extends Base
{
    protected $table = 'user_group_description';
    protected $primaryKey = ['user_group_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'user_group_id',
        'language_code',
        'name',
        'description',
    ];
}
