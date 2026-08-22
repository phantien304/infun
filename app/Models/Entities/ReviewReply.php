<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewReply extends Base
{
    use SoftDeletes;

    public const AUTHOR_CUSTOMER = 0;
    public const AUTHOR_SHOP     = 1;
    public const AUTHOR_ADMIN    = 2;

    protected $table = 'review_reply';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $fillable = [
        'review_id',
        'parent_reply_id',
        'user_id',
        'author_type',
        'text',
        'is_publish',
    ];

    protected $casts = [
        'is_publish'  => 'boolean',
        'author_type' => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_reply_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_reply_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
