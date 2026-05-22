<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class IngredientSafety extends Base
{
    protected $table = 'ingredient_safety';
    protected $primaryKey = ['ingredient_id', 'safety_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function safety()
    {
        return $this->belongsTo(Safety::class, 'safety_id', 'id');
    }
}
