<?php

namespace App\Models\Entities;

use App\Enums\ReviewStatus;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Base
{
    use SoftDeletes;

    protected $table = 'review';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $fillable = [
        'product_id', 'product_variant_id', 'order_id', 'user_id',
        'ip', 'author', 'email', 'title', 'text', 'rating', 'status',
        'is_publish', 'is_anonymous', 'language_code', 'source', 'user_agent',
    ];

    protected $casts = [
        'is_anonymous'    => 'boolean',
        'is_publish'      => 'boolean',
        'status'          => 'integer',
        'helpful_count'   => 'integer',
        'unhelpful_count' => 'integer',
        'reply_count'     => 'integer',
        'media_count'     => 'integer',
        'edit_count'      => 'integer',
        'last_edited_at'  => 'datetime',
        'approved_at'     => 'datetime',
    ];

    public function scopeApproved($q)
    {
        return $q->where('status', ReviewStatus::Approved->value);
    }

    public function scopeForProduct($q, int $productId)
    {
        return $q->where('product_id', $productId);
    }

    public function scopeWithMedia($q)
    {
        return $q->where('media_count', '>', 0);
    }

    public function scopeWithText($q)
    {
        return $q->whereNotNull('text')->where('text', '!=', '');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function reviewRatings()
    {
        return $this->hasMany(ReviewRating::class, 'review_id', 'id');
    }

    public function reviewMedia()
    {
        return $this->hasMany(ReviewMedia::class, 'review_id', 'id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function reviewReplies()
    {
        return $this->hasMany(ReviewReply::class, 'review_id', 'id')
            ->whereNull('parent_reply_id')
            ->where('is_publish', true)
            ->orderBy('created_at');
    }

    public function reviewHelpfuls()
    {
        return $this->hasMany(ReviewHelpful::class, 'review_id', 'id');
    }

    public function reviewTags()
    {
        return $this->belongsToMany(
            ReviewTag::class,
            'review_tag_pivot',
            'review_id',
            'review_tag_id',
        );
    }

    public function reviewReports()
    {
        return $this->hasMany(ReviewReport::class, 'review_id', 'id');
    }
}
