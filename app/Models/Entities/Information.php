<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Information extends Base
{
    use SoftDeletes;
    protected $table = 'information';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'banner_id',
        'sort_order',
    ];

    public function descriptions()
    {
        return $this->hasMany(InformationDescription::class, 'information_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(InformationDescription::class, 'information_id', 'id')->forLocale();
    }
}
