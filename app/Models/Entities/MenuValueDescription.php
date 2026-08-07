<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class MenuValueDescription extends Base
{
    protected $table = 'menu_value_description';
    protected $primaryKey = ['menu_value_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = ['menu_value_id', 'language_code', 'link', 'title'];

    public function menuValue()
    {
        return $this->belongsTo(MenuValue::class, 'menu_value_id', 'id');
    }
}
