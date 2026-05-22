<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAddress extends Base
{
    use SoftDeletes;
    protected $table = 'user_address';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }

    public function district()
    {
        return $this->belongsTo(District::class, 'district_id', 'id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'ward_id', 'id');
    }
}
