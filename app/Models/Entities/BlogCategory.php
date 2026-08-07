<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogCategory extends Base
{
    use SoftDeletes;
    protected $table = 'blog_category';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'parent_id',
        'banner_id',
        'icon',
        'image',
        'deleted_at'
    ];

    public function description()
    {
        return $this->hasOne(BlogCategoryDescription::class, 'category_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(BlogCategoryDescription::class, 'category_id', 'id');
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class, 'category_id', 'id');
    }
}
