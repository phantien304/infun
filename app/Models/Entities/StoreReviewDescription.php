<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class StoreReviewDescription extends Base
{
    protected $table = 'store_review_description';
    public $incrementing = false;
    public $timestamps = true;

    public function storeReview()
    {
        return $this->belongsTo(StoreReview::class, 'store_review_id', 'id');
    }
}
