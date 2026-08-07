<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class BlogCategoryDescription extends Base
{
    protected $table = 'blog_category_description';
    public $primaryKey = ['category_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'category_id', 'language_code', 'title', 'description',
        'slug', 'meta_title', 'meta_description',
    ];
}
