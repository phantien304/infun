<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Base
{
    use SoftDeletes;
    protected $table = 'attribute';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['attributeValues'];

    public function attributeValues()
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id', 'id');
    }

    public function descriptions()
    {
        return $this->hasMany(AttributeDescription::class, 'attribute_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(AttributeDescription::class, 'attribute_id', 'id')->forLocale();
    }
}
