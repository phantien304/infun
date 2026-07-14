<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            App\Http\Middleware\HttpsProtocol::class,
            App\Http\Middleware\TransformApiHeaders::class,
            App\Http\Middleware\CheckForMaintenanceMode::class,
        ]);
        $middleware->web(append: [
            App\Http\Middleware\VerifyCsrfToken::class,
            App\Http\Middleware\DetectArea::class,
            App\Http\Middleware\SetLocale::class,
            App\Http\Middleware\TrackAffiliateRef::class,
        ]);
        $middleware->alias([
            'check_js_request' => \App\Http\Middleware\CheckJsRequest::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'check_login' => \App\Http\Middleware\CheckLogin::class,
            'maintenance' => \App\Http\Middleware\Maintenance::class,
            'cache_page' => \App\Http\Middleware\CachePage::class,
            'limit_access' => \App\Http\Middleware\LimitAccess::class,
            // Sanctum: kiểm tra ability/scope của token (vd 'abilities:mobile').
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            // Tự phân quyền REST CMS theo spatie (BaseCmsController + macro cmsApiResource).
            'cms.permission' => \App\Http\Middleware\CmsPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })
    ->create();
