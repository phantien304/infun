<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Effect extends Base
{
    use SoftDeletes;
    protected $table = 'effect';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['ingredientEffects'];

    public function ingredientEffects()
    {
        return $this->hasMany(IngredientEffect::class, 'effect_id', 'id');
    }
}
