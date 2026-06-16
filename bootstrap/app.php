<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role'                    => \App\Http\Middleware\CheckRole::class,
            'maintenance'             => \App\Http\Middleware\MaintenanceModeCheck::class,
            'ensure.password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\MaintenanceModeCheck::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePasswordChanged::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            // Only trace unexpected server errors — skip auth, validation, HTTP 4xx
            if ($e instanceof \Illuminate\Auth\AuthenticationException) return;
            if ($e instanceof \Illuminate\Validation\ValidationException) return;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) return;
            if (method_exists($e, 'getStatusCode') && $e->getStatusCode() < 500) return;

            try {
                \App\Services\TraceService::error($e->getMessage(), [
                    'class' => get_class($e),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'url'   => request()->fullUrl(),
                ]);
            } catch (\Throwable) {}
        });
    })->create();
