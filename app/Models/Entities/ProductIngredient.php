<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductIngredient extends Base
{
    protected $table = 'product_ingredient';
    public $primaryKey = ['product_id', 'ingredient_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'id');
    }
}
