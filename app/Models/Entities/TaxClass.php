<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxClass extends Base
{
    use SoftDeletes;
    protected $table = 'tax_class';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected static array $destroyRelations = ['taxRules'];

    public function taxRules()
    {
        return $this->hasMany(TaxRule::class, 'tax_class_id', 'id');
    }
}
