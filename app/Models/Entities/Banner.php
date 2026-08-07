<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use App\Models\Traits\HasTranslation;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Base
{
    use SoftDeletes;
    use HasTranslation;
    protected $table = 'banner';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['bannerValues'];
    protected $fillable = [
        'position',
        'page',
        'type',
        'sort_order',
        'theme',
        'deleted_at',
    ];

    public function bannerValues()
    {
        return $this->hasMany(BannerValue::class, 'banner_id', 'id');
    }

    public function descriptions()
    {
        return $this->hasMany(BannerDescription::class, 'banner_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(BannerDescription::class, 'banner_id', 'id')->forLocale();
    }
}
