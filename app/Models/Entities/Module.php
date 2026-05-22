<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Base
{
    use SoftDeletes;
    protected $table = 'module';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
