<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuValue extends Base
{
    use SoftDeletes;
    protected $table = 'menu_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['descriptions'];
    protected $fillable = [
        'menu_id', 'item_id', 'parent_id', 'position', 'type', 'css',
        'html_custom', 'mega_menu', 'tab_content', 'image', 'status',
        'deleted_at'
    ];

    public function descriptions()
    {
        return $this->hasMany(MenuValueDescription::class, 'menu_value_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(MenuValueDescription::class, 'menu_value_id', 'id')->forLocale();
    }
}
