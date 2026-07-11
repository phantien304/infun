<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Affiliate extends Base
{
    protected $table = 'affiliate';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $casts = [
        'commission_rate' => 'decimal:2',
        'approved_at'     => 'datetime',
    ];

    /**
     * KHÔNG dùng cast 'array' cho payment_info: Base::save() refill raw
     * attributes (setRawAttributes([])->fill($attrs)) khiến json cast encode
     * LẦN 2 → DB lưu double-encoded, đọc ra string thay vì array.
     * Mutator idempotent (string đã encode giữ nguyên) nên sống sót refill.
     */
    protected function paymentInfo(): Attribute
    {
        return Attribute::make(
            get: static function ($value) {
                if (! is_string($value) || $value === '') {
                    return null;
                }
                $decoded = json_decode($value, true);

                // Data cũ có thể đã lưu double-encoded (bug trước khi fix)
                // → decode ra string JSON thì decode thêm lần nữa.
                return is_string($decoded) ? json_decode($decoded, true) : $decoded;
            },
            set: static fn ($value) => is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : $value,
        );
    }
    protected static array $destroyRelations = [
        'affiliateLinks', 'affiliateClicks', 'affiliateConversions', 'affiliatePayouts',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class, 'affiliate_id', 'id');
    }

    public function affiliateClicks()
    {
        return $this->hasMany(AffiliateClick::class, 'affiliate_id', 'id');
    }

    public function affiliateConversions()
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_id', 'id');
    }

    public function affiliatePayouts()
    {
        return $this->hasMany(AffiliatePayout::class, 'affiliate_id', 'id');
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'affiliate_coupon', 'affiliate_id', 'coupon_id');
    }
}
