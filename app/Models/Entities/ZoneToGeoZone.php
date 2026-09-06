<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ZoneToGeoZone extends Base
{
    protected $table = 'zone_to_geo_zone';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function geoZone()
    {
        return $this->belongsTo(GeoZone::class, 'geo_zone_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
}
