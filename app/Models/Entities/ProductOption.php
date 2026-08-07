<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOption extends Base
{
    use SoftDeletes;

    protected $table = 'product_option';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['productOptionValues'];
    protected $fillable = ['product_id', 'option_id', 'value', 'required', 'price', 'deleted_at'];

    public function option()
    {
        return $this->belongsTo(Option::class, 'option_id', 'id');
    }

    public function productOptionValues()
    {
        return $this->hasMany(ProductOptionValue::class, 'product_option_id', 'id');
    }
}
