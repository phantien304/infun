<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class IngredientEffect extends Base
{
    protected $table = 'ingredient_effect';
    protected $primaryKey = ['ingredient_id', 'effect_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function effect()
    {
        return $this->belongsTo(Effect::class, 'effect_id', 'id');
    }
}
