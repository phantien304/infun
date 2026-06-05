<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewHelpful extends Base
{
    public const VOTE_HELPFUL   = 1;
    public const VOTE_UNHELPFUL = -1;
    public const VOTE_WITHDRAWN = 0;

    protected $table = 'review_helpful';
    public $incrementing = true;
    public $timestamps = true;

    protected $casts = [
        'vote_type' => 'integer',
        'user_id'   => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'id');
    }
}
