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
    // DOKTIS Office et DOKTIS Patient s'authentifient via cookie de session
    // Sanctum (SPA stateful), donc l'auth des canaux privés utilise ce garde.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'staff' => \App\Http\Middleware\EnsureStaffUser::class,
            'patient.account' => \App\Http\Middleware\EnsurePatientAccount::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        // Authentifie les requêtes de nos SPA (Office, Patient, Directory)
        // par cookie de session HttpOnly plutôt que par jeton Bearer exposé
        // au JavaScript : ajoute EnsureFrontendRequestsAreStateful (cookies
        // chiffrés + session + CSRF) devant le groupe "api" pour les
        // domaines listés dans SANCTUM_STATEFUL_DOMAINS.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
