<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogTag extends Base
{
    use SoftDeletes;
    protected $table = 'blog_tag';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'background',
        'sort_order',
        'deleted_at'
    ];

    public function description()
    {
        return $this->hasOne(BlogTagDescription::class, 'blog_tag_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(BlogTagDescription::class, 'blog_tag_id', 'id');
    }
}
