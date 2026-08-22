<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manufacturer extends Base
{
    use SoftDeletes;
    protected $table = 'manufacturer';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'name',
        'image',
        'sort_order',
        'meta_title',
        'meta_description',
    ];
}
