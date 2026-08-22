<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Base
{
    use SoftDeletes;
    protected $table = 'contact';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'name',
        'email',
        'company',
        'phone',
        'address',
        'service',
        'content',
        'is_read',
    ];
}
