<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreReview extends Base
{
    use SoftDeletes;
    protected $table = 'store_review';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    public function descriptions()
    {
        return $this->hasMany(StoreReviewDescription::class, 'store_review_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(StoreReviewDescription::class, 'store_review_id', 'id')->forLocale();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
