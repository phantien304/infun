<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class TaxRule extends Base
{
    protected $table = 'tax_rule';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
}
