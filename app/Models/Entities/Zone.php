<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Base
{
    use SoftDeletes;
    protected $table = 'zone';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['districts'];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ZoneDescription::class, 'zone_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(ZoneDescription::class, 'zone_id', 'id');
    }

    public function districts()
    {
        return $this->hasMany(District::class, 'zone_id', 'id');
    }
}
