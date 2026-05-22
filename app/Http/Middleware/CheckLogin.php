<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckLogin
{
    public function handle($request, Closure $next)
    {
        //if (!Auth::check()) {
        //    return errValidator(trans('auth.not_exists'), 401);
        //}
        return $next($request);
    }
}
