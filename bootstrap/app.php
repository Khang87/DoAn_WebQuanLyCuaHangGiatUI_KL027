<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RejectCustomerRole;
use App\Http\Middleware\RestoreRememberedLogin;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
                | Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
                | Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
                | Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'reject.customer' => RejectCustomerRole::class,
        ]);
        $middleware->web(append: [RestoreRememberedLogin::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, RestoreRememberedLogin::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Vercel terminates HTTPS before forwarding requests to the PHP runtime.
$isVercel = isset($_ENV['VERCEL'])
    || isset($_SERVER['VERCEL'])
    || getenv('VERCEL') !== false;

if ($isVercel) {
    $app->useStoragePath('/tmp/storage');
    $app->booted(static function (): void {
        URL::forceScheme('https');
    });
}

return $app;
