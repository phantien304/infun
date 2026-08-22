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
    protected static array $destroyRelations = ['ingredientEffects', 'ingredientSafeties', 'ingredientSkincares', 'productIngredients'];
    protected $fillable = [
        'name',
        'description',
        'warning',
        'warning_text',
    ];

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
