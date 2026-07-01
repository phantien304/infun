<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewMedia extends Base
{
    use SoftDeletes;

    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    protected $table = 'review_media';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'duration'   => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
        'file_size'  => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'id');
    }
}
