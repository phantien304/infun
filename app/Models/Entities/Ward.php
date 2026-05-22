<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ward extends Base
{
    use SoftDeletes;
    protected $table = 'ward';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function description()
    {
        return $this->hasOne(WardDescription::class, 'ward_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(WardDescription::class, 'ward_id', 'id');
    }

    public function district()
    {
        return $this->belongsTo(District::class, 'district_id', 'id');
    }
}
