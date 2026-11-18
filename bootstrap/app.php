<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // <-- LIGNE AJOUTÉE
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Exclure les routes agent de la vérification CSRF
        $middleware->validateCsrfTokens(except: [
            'api/agent/*',
        ]);

        // Alias des middlewares
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'verified.email' => \App\Http\Middleware\ForceEmailVerification::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();