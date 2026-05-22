<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Base
{
    use SoftDeletes;
    protected $table = 'payment';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function descriptions()
    {
        return $this->hasMany(PaymentDescription::class, 'payment_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(PaymentDescription::class, 'payment_id', 'id')->forLocale();
    }
}
