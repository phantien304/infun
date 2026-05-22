<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class BlogTagDescription extends Base
{
    protected $table = 'blog_tag_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['blog_tag_id', 'language_code'];

    public function blogTag()
    {
        $this->belongsTo(BlogTag::class, 'id', 'blog_tag_id');
    }
}
