<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;

abstract class BaseCmsController extends Controller
{
    protected string $permission = '';

    public function permissionName(): string
    {
        return $this->permission;
    }
}
