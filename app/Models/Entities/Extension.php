<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Extension extends Base
{
    use SoftDeletes;
    protected $table = 'extension';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
