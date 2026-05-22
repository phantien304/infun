<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class PaymentDescription extends Base
{
    protected $table = 'payment_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['payment_id', 'language_code'];
}
