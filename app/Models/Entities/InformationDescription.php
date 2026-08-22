<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class InformationDescription extends Base
{
    protected $table = 'information_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['information_id', 'language_code'];
    protected $fillable = [
        'information_id',
        'language_code',
        'title',
        'description',
        'content',
        'meta_title',
        'meta_description',
    ];
}
