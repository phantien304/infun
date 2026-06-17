<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Safety extends Base
{
    use SoftDeletes;
    protected $table = 'safety';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['ingredientSafeties'];

    public function ingredientSafeties()
    {
        return $this->hasMany(IngredientSafety::class, 'safety_id', 'id');
    }
}
