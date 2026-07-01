<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewReport extends Base
{
    protected $table = 'review_report';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $casts = [
        'status'       => 'integer',
        'reported_by'  => 'integer',
        'resolved_by'  => 'integer',
        'resolved_at'  => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'id');
    }
}
