<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class UserResetPassword extends Base
{
    protected $table = 'user_reset_password';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
