<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Base
{
    use SoftDeletes;
    protected $table = 'ingredient';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $destroyRelations = ['ingredientEffects', 'ingredientSafeties', 'ingredientSkincares', 'productIngredients'];

    public function ingredientEffects()
    {
        return $this->hasMany(IngredientEffect::class, 'ingredient_id', 'id');
    }

    public function ingredientSafeties()
    {
        return $this->hasMany(IngredientSafety::class, 'ingredient_id', 'id');
    }

    public function ingredientSkincares()
    {
        return $this->hasMany(IngredientSkincare::class, 'ingredient_id', 'id');
    }

    public function productIngredients()
    {
        return $this->hasMany(ProductIngredient::class, 'ingredient_id', 'id');
    }
}
