<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class IngredientSkincare extends Base
{
    protected $table = 'ingredient_skincare';
    protected $primaryKey = ['ingredient_id', 'skincare_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function skincare()
    {
        return $this->belongsTo(Skincare::class, 'skincare_id', 'id');
    }
}
