<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Base
{
    use SoftDeletes;
    protected $table = 'tax_rate';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected static $destroyRelations = ['taxRateToUserGroups'];

    public function taxRateToUserGroups()
    {
        return $this->hasMany(TaxRateToUserGroup::class, 'tax_rate_id', 'id');
    }

    public function geoZone()
    {
        return $this->belongsTo(GeoZone::class, 'geo_zone_id', 'id');
    }
}
