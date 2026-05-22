<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class MenuValue extends Base
{
    protected $table = 'menu_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['descriptions'];

    public function descriptions()
    {
        return $this->hasMany(MenuValueDescription::class, 'menu_value_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(MenuValueDescription::class, 'menu_value_id', 'id')->forLocale();
    }
}
