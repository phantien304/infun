<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductDescription extends Base
{
    protected $table = 'product_description';
    public $primaryKey = ['product_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'product_id', 'language_code', 'name', 'slug', 'description',
        'content', 'tag', 'meta_title', 'meta_description', 'meta_keyword',
    ];
}
