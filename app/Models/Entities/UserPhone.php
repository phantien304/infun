<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class UserPhone extends Base
{
    protected $table = 'user_phone';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
