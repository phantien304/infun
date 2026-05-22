<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class LengthClass extends Base
{
    use SoftDeletes;
    protected $table = 'length_class';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function descriptions()
    {
        return $this->hasMany(LengthClassDescription::class, 'length_class_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(LengthClassDescription::class, 'length_class_id', 'id')->forLocale();
    }
}
