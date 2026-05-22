<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class TaxRateToUserGroup extends Base
{
    protected $table = 'tax_rate_to_user_group';
    protected $primaryKeyAutoIncrement = ['tax_rate_id', 'user_group_id'];
    public $incrementing = false;
    public $timestamps = false;
}
