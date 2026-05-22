<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class UserWishlist extends Base
{
    protected $table = 'user_wishlist';
    protected $primaryKey = ['user_id', 'product_id'];
    public $timestamps = false;
    public $incrementing = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
