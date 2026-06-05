<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewCriteriaDescription extends Base
{
    protected $table = 'review_criteria_description';
    public $incrementing = true;
    public $timestamps = true;

    public function criteria()
    {
        return $this->belongsTo(ReviewCriteria::class, 'review_criteria_id', 'id');
    }
}
