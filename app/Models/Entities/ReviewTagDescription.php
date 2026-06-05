<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewTagDescription extends Base
{
    protected $table = 'review_tag_description';
    public $incrementing = true;
    public $timestamps = true;

    public function tag()
    {
        return $this->belongsTo(ReviewTag::class, 'review_tag_id', 'id');
    }
}
