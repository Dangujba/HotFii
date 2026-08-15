<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['webhooks/paystack']);

        $middleware->alias([
            'organization' => \App\Http\Middleware\EnsureOrganizationContext::class,
            'role' => \App\Http\Middleware\RequireOrganizationRole::class,
            'platform-admin' => \App\Http\Middleware\RequirePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions use Laravel's standard rendering and logging.
    })
    ->create();