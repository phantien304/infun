<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class UserPhone extends Base
{
    protected $table = 'user_phone';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'user_id',
        'phone',
        'nation_phone_code',
        'is_verify',
    ];
}
