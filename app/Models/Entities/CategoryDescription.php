<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class CategoryDescription extends Base
{
    protected $table = 'category_description';
    public $primaryKey = ['category_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
