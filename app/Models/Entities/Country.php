<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Base
{
    use SoftDeletes;
    protected $table = 'country';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['zones'];

    public function zones()
    {
        return $this->hasMany(Zone::class, 'country_id', 'id');
    }
}
