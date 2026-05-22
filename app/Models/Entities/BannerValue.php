<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class BannerValue extends Base
{
    protected $table = 'banner_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['bannerValueDescriptions'];

    public function descriptions()
    {
        return $this->hasMany(BannerValueDescription::class, 'banner_value_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(BannerValueDescription::class, 'banner_value_id', 'id')->forLocale();
    }
}
