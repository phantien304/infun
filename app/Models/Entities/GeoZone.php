<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeoZone extends Base
{
    use SoftDeletes;
    protected $table = 'geo_zone';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected static $destroyRelations = ['zoneToGeoZones'];

    public function zoneToGeoZones()
    {
        return $this->hasMany(ZoneToGeoZone::class, 'geo_zone_id', 'id');
    }

    public function taxRates()
    {
        return $this->hasMany(TaxRate::class, 'geo_zone_id', 'id');
    }
}
