<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeightClass extends Base
{
    use SoftDeletes;
    protected $table = 'weight_class';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function descriptions()
    {
        return $this->hasMany(WeightClassDescription::class, 'weight_class_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(WeightClassDescription::class, 'weight_class_id', 'id')->forLocale();
    }
}
