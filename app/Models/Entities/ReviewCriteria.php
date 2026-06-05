<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use App\Models\Traits\HasAdvancedScopes;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewCriteria extends Base
{
    use SoftDeletes;
    use HasAdvancedScopes;

    protected $table = 'review_criteria';
    public $incrementing = true;
    public $timestamps = true;

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function descriptions()
    {
        return $this->hasMany(ReviewCriteriaDescription::class, 'review_criteria_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ReviewCriteriaDescription::class, 'review_criteria_id', 'id')->forLocale();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }
}
