<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class FilterValue extends Base
{
    protected $table = 'filter_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['productFilters'];
    protected $fillable = [
        'filter_id',
        'sort_order',
    ];

    public function descriptions()
    {
        return $this->hasMany(FilterValueDescription::class, 'filter_value_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(FilterValueDescription::class, 'filter_value_id', 'id')->forLocale();
    }

    public function productFilters()
    {
        return $this->hasMany(ProductFilter::class, 'filter_value_id', 'id');
    }
}
