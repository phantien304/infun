<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class UserReward extends Base
{
    protected $table = 'user_reward';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }
}
