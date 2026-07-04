<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class  Currency extends Base
{
    use SoftDeletes;
    protected $table = 'currency';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $casts = [
        'value'         => 'float',
        'decimal_place' => 'integer',
        'sort_order'    => 'integer',
    ];
}
