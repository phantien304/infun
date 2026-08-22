<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class OptionValue extends Base
{
    use SoftDeletes;
    protected $table = 'option_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['productOptionValues'];
    protected $fillable = [
        'option_id',
        'image',
        'sort_order',
    ];

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
