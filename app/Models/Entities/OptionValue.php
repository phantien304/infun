<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OptionValue extends Base
{
    protected $table = 'option_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $_destroyRelations = ['productOptionValues'];

    public function descriptions()
    {
        return $this->hasMany(OptionValueDescription::class, 'option_value_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(OptionValueDescription::class, 'option_value_id', 'id')->forLocale();
    }

    public function productOptionValues()
    {
        return $this->hasMany(ProductOptionValue::class, 'option_value_id', 'id');
    }
}
