<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Orders extends Base implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'orders';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $auditExclude = ['fee', 'updated_at'];

    protected $fillable = [
        'invoice_no', 'invoice_prefix', 'app_trans_id', 'zp_refund_id', 'zp_trans_id',
        'carrier_order_id', 'user_id', 'user_address_id', 'full_name', 'email',
        'telephone', 'address', 'country', 'country_id', 'zone', 'zone_id',
        'district', 'district_id', 'ward', 'ward_id', 'payment_method', 'payment_code',
        'carrier_code', 'comment', 'voucher', 'coupon', 'length_class_id', 'width',
        'height', 'length', 'weight_class_id', 'weight', 'total', 'order_status_id',
        'affiliate_id', 'language_code', 'currency_id', 'currency_code', 'currency_value',
        'fee', 'ip', 'forwarded_ip', 'user_agent', 'accept_language', 'chanel',
        'is_read', 'idempotency_key', 'deleted_at',
    ];
    protected static array $destroyRelations = [
        'ordersCancels',
        'ordersHistories',
        'ordersProducts',
        'ordersStatusLogs',
        'voucherHistories',
        'ordersTotals'
    ];

    public function ordersProducts()
    {
        return $this->hasMany(OrdersProduct::class, 'order_id', 'id');
    }

    public function ordersHistories()
    {
        return $this->hasMany(OrdersHistory::class, 'order_id', 'id');
    }

    public function ordersCancels()
    {
        return $this->hasMany(OrdersCancel::class, 'order_id', 'id');
    }

    public function ordersStatus()
    {
        return $this->belongsTo(OrdersStatus::class, 'order_status_id', 'id');
    }

    public function ordersTotals()
    {
        return $this->hasMany(OrdersTotal::class, 'order_id', 'id');
    }

    public function ordersTotal()
    {
        return $this->belongsTo(OrdersTotal::class, 'id', 'order_id');
    }

    public function ordersStatusLogs()
    {
        return $this->hasMany(OrdersStatusLog::class, 'order_id', 'id');
    }

    public function voucherHistories()
    {
        return $this->hasMany(VoucherHistory::class, 'order_id', 'id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_code', 'code');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_code', 'code');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
