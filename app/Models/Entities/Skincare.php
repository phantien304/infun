<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Skincare extends Base
{
    use SoftDeletes;
    protected $table = 'skincare';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $_destroyRelations = ['ingredientSkincares'];

    public function ingredientSkincares()
    {
        return $this->hasMany(IngredientSkincare::class, 'skincare_id', 'id');
    }
}
