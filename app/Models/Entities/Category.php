<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Base
{
    use SoftDeletes;
    protected $table = 'category';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['productCategories', 'couponCategories'];

    public function description()
    {
        return $this->hasOne(CategoryDescription::class, 'category_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(CategoryDescription::class, 'category_id', 'id');
    }

    public function productCategories()
    {
        return $this->hasMany(ProductCategory::class, 'category_id', 'id');
    }

    public function couponCategories()
    {
        return $this->hasMany(CouponCategory::class, 'category_id', 'id');
    }
}
