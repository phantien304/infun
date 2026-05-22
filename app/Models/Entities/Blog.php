<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Base
{
    use SoftDeletes;
    protected $table = 'blog';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function blogCategory()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id', 'id');
    }
    public function descriptions()
    {
        return $this->hasMany(BlogDescription::class, 'blog_id', 'id');
    }
    public function description()
    {
        return $this->hasOne(BlogDescription::class, 'blog_id', 'id')->forLocale();
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }
}
