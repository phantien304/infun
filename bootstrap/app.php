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
        $middleware->trustProxies(at: '*');

        $middleware->append([
            App\Http\Middleware\HttpsProtocol::class,
            App\Http\Middleware\TransformApiHeaders::class,
            App\Http\Middleware\CheckForMaintenanceMode::class,
        ]);
        $middleware->web(append: [
            App\Http\Middleware\VerifyCsrfToken::class,
            App\Http\Middleware\DetectArea::class,
            App\Http\Middleware\SetLocale::class,
            App\Http\Middleware\ResolveTheme::class,
            App\Http\Middleware\TrackAffiliateRef::class,
        ]);
        $middleware->api(prepend: [
            Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->alias([
            'check_js_request' => \App\Http\Middleware\CheckJsRequest::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'check_login' => \App\Http\Middleware\CheckLogin::class,
            'maintenance' => \App\Http\Middleware\Maintenance::class,
            'cache_page' => \App\Http\Middleware\CachePage::class,
            'limit_access' => \App\Http\Middleware\LimitAccess::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'cms.permission' => \App\Http\Middleware\CmsPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })
    ->create();
