<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Base
{
    use SoftDeletes;
    protected $table = 'menu';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['menuValues'];
    protected $fillable = [
        'title',
        'position',
        'theme',
        'deleted_at'
    ];

    public function menuValues()
    {
        return $this->hasMany(MenuValue::class, 'menu_id', 'id');
    }
}
