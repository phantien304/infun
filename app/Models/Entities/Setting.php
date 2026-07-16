<?php

namespace App\Models\Entities;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        // 1 = được trả ra GET /rcms/system/init (endpoint public, trước auth);
        // 0 = chỉ dùng server-side. Admin chỉnh cờ này để ẩn setting khỏi SPA.
        'cms_public' => 'boolean',
    ];

    public function getParsedValueAttribute()
    {
        if ($this->serialized || is_numeric($this->value)) {
            $decoded = json_decode($this->value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->value;
        }

        return $this->value;
    }
}
