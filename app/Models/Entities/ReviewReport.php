<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ReviewReport extends Base
{
    public const STATUS_PENDING   = 0;
    public const STATUS_RESOLVED  = 1;
    public const STATUS_DISMISSED = 2;

    protected $table = 'review_report';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $fillable = [
        'review_id',
        'reported_by',
        'reason_code',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

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
