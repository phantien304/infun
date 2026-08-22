<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewCriteriaDescription extends Base
{
    protected $table = 'review_criteria_description';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $fillable = [
        'review_criteria_id',
        'language_code',
        'name',
        'hint',
    ];

    public function criteria()
    {
        return $this->belongsTo(ReviewCriteria::class, 'review_criteria_id', 'id');
    }
}
