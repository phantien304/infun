<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipping extends Base
{
    use SoftDeletes;
    protected $table = 'shipping';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function descriptions()
    {
        return $this->hasMany(ShippingDescription::class, 'shipping_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ShippingDescription::class, 'shipping_id', 'id')->forLocale();
    }
}
