<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductReward extends Base
{
    protected $table = 'product_reward';
    protected $primaryKey = ['product_id', 'user_group_id'];
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['product_id', 'user_group_id', 'points'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'id', 'product_id');
    }
}
