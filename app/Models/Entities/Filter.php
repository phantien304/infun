<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Filter extends Base
{
    use SoftDeletes;
    protected $table = 'filter';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['filterValues'];
    protected $fillable = [
        'sort_order',
    ];

    public function filterValues()
    {
        return $this->hasMany(FilterValue::class, 'filter_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(FilterDescription::class, 'filter_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(FilterDescription::class, 'filter_id', 'id');
    }
}
