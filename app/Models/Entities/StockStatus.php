<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockStatus extends Base
{
    use SoftDeletes;
    protected $table = 'stock_status';
    protected $primaryKeyAutoIncrement = ['id', 'language_code'];
    public $timestamps = true;
}
