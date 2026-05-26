<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $reviewDomain = env('REVIEW_DOMAIN');

            if ($reviewDomain) {
                // Subdomain mode: review.bellflowapp.com/*
                Route::middleware(['web', 'auth'])
                    ->domain($reviewDomain)
                    ->group(base_path('routes/review-portal.php'));
            } else {
                // Path-prefix mode: bellflowapp.com/review/*
                Route::middleware(['web', 'auth'])
                    ->prefix('review')
                    ->group(base_path('routes/review-portal.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token' => \App\Http\Middleware\ApiTokenMiddleware::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
