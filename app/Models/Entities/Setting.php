<?php

namespace App\Models\Entities;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function getParsedValueAttribute()
    {
        if ($this->serialized || is_numeric($this->value)) {
            $decoded = json_decode($this->value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->value;
        }

        return $this->value;
    }
}
