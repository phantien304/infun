<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdersStatus extends Base
{
    use SoftDeletes;
    protected $table = 'orders_status';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['ordersStatusCarrierOrders'];
    protected $fillable = [
        'language_code',
        'name',
        'deleted_at'
    ];

    public function ordersStatusCarrierOrders()
    {
        return $this->hasMany(OrdersStatusCarrierOrder::class, 'orders_status_id', 'id');
    }
}
