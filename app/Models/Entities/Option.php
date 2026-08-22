<?php

namespace App\Models\Entities;

use App\Enums\OptionRole;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Option extends Base
{
    use SoftDeletes;

    protected $table = 'option';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $casts = ['role' => OptionRole::class];
    protected static array $destroyRelations = ['optionValues', 'productOptions'];
    protected $fillable = [
        'type',
        'role',
        'sort_order',
    ];

    public function isVariant(): bool
    {
        return $this->role?->isVariant() ?? false;
    }

    public function isCustomField(): bool
    {
        return $this->role?->isCustomField() ?? true;
    }

    public function optionValues()
    {
        return $this->hasMany(OptionValue::class, 'option_id', 'id');
    }

    public function descriptions()
    {
        return $this->hasMany(OptionDescription::class, 'option_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(OptionDescription::class, 'option_id', 'id')->forLocale();
    }

    public function productOptions()
    {
        return $this->hasMany(ProductOption::class, 'option_id', 'id');
    }
}
