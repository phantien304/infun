<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Province extends Base
{
    use SoftDeletes;
    protected $table = 'province';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function description()
    {
        return $this->hasOne(ProvinceDescription::class, 'province_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(ProvinceDescription::class, 'province_id', 'id');
    }
}
