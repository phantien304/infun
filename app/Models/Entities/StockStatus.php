<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockStatus extends Base
{
    use SoftDeletes;
    protected $table = 'stock_status';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function descriptions()
    {
        return $this->hasMany(StockStatusDescription::class, 'stock_status_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(StockStatusDescription::class, 'stock_status_id', 'id')->forLocale();
    }
}
