<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductDraft extends Base
{
    use SoftDeletes;
    protected $table = 'product_draft';
    protected $primaryKeyAutoIncrement = 'id';
    protected $isForceDeleting;
    public $incrementing = true;
    public $timestamps = true;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
