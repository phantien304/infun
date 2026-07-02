<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use App\Models\Traits\HasAdvancedScopes;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewTag extends Base
{
    use SoftDeletes, HasAdvancedScopes;

    protected $table = 'review_tag';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $casts = [
        'usage_count'       => 'integer',
        'is_auto_generated' => 'boolean',
        'is_active'         => 'boolean',
    ];

    public function descriptions()
    {
        return $this->hasMany(ReviewTagDescription::class, 'review_tag_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ReviewTagDescription::class, 'review_tag_id', 'id')->forLocale();
    }

    public function reviews()
    {
        return $this->belongsToMany(
            Review::class,
            'review_tag_pivot',
            'review_tag_id',
            'review_id',
        );
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('usage_count', 'desc');
    }
}
