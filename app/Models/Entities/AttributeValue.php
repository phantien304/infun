<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AttributeValue extends Base
{
    protected $table = 'attribute_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['productAttributes'];

    public function descriptions()
    {
        return $this->hasMany(AttributeValueDescription::class, 'attribute_value_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(AttributeValueDescription::class, 'attribute_value_id', 'id')->forLocale();
    }

    public function productAttributes()
    {
        return $this->hasMany(ProductAttribute::class, 'attribute_id', 'id');
    }
}
