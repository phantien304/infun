<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Base
{
    use SoftDeletes;
    protected $table = 'language';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
