<?php

use App\Http\Middleware\EnsureActiveSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireMfa;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureActiveSession::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias(['mfa' => RequireMfa::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
