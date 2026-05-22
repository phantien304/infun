<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ShippingDescription extends Base
{
    protected $table = 'shipping_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['shipping_id', 'language_code'];
}
