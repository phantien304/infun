<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class BlogDescription extends Base
{
    protected $table =  'blog_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['blog_id', 'language_code'];
    protected $fillable = [
        'blog_id', 'language_code', 'title', 'description', 'content',
        'slug', 'tag', 'meta_title', 'meta_description',
    ];

    public function blog()
    {
        $this->belongsTo(Blog::class, 'id', 'blog_id');
    }
}
