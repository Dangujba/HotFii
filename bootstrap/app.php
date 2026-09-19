<?php

use App\Http\Middleware\EnsureMobileOrganizationMembership;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Http\Middleware\RequireOrganizationRole;
use App\Http\Middleware\RequirePlatformAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

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
            'organization' => EnsureOrganizationContext::class,
            'role' => RequireOrganizationRole::class,
            'platform-admin' => RequirePlatformAdmin::class,
            'abilities' => CheckAbilities::class,
            'mobile-organization' => EnsureMobileOrganizationMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions use Laravel's standard rendering and logging.
    })
    ->create();
