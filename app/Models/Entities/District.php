<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class District extends Base
{
    use SoftDeletes;
    protected $table = 'district';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $_destroyRelations = ['wards'];

    public function description()
    {
        return $this->hasOne(DistrictDescription::class, 'district_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(DistrictDescription::class, 'district_id', 'id');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id', 'id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }

    public function wards()
    {
        return $this->hasMany(Ward::class, 'district_id', 'id');
    }
}
