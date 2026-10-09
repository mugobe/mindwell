<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum: allow the Flutter app to authenticate via bearer tokens
        // while Blade keeps using the default session/cookie guard.
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Phase 0 launch-safety (2026-10-05):
        // - Coolify puts a proxy in front of the app, so trust it — otherwise Laravel
        //   thinks every request is plain http (wrong links, secure cookies not sent).
        // - Flutterwave's server-to-server webhook can't send a CSRF token.
        // - Security headers on every response.
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['webhooks/flutterwave']);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();