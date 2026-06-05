<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewRating extends Base
{
    protected $table = 'review_rating';
    public $incrementing = false;
    public $timestamps = true;
    protected $primaryKey = ['review_id', 'review_criteria_id'];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'id');
    }

    public function reviewCriteria()
    {
        return $this->belongsTo(ReviewCriteria::class, 'review_criteria_id', 'id');
    }
}
