<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAppUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'app-user' => EnsureAppUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The whole API speaks JSON, regardless of the Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('v1/*') || $request->expectsJson());
    })->create();
