<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\SyncWhatnotReporting;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SyncWhatnotReporting::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [\App\Http\Middleware\PerformanceHeaders::class], append: [\App\Http\Middleware\RequirePasswordChange::class]);
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
        $middleware->alias([
            'api.token' => \App\Http\Middleware\ApiTokenMiddleware::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Livewire authorization failures render the branded 403 page and may
        // not appear in laravel.log. Record the actual rejecting class/line
        // without logging request payloads, cookies, or component state.
        $exceptions->reportable(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e): void {
            if ($e->getStatusCode() !== 403 || ! request()->is('livewire*')) return;
            \Illuminate\Support\Facades\Log::error('Livewire 403 diagnostic', [
                'path' => request()->path(),
                'user_id' => auth()->id(),
                'roles' => auth()->user()?->getRoleNames()->all(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(7)->map(fn ($frame) => [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                ])->all(),
            ]);
        });
        $exceptions->reportable(function (\Illuminate\Auth\Access\AuthorizationException $e): void {
            if (! request()->is('livewire*')) return;
            \Illuminate\Support\Facades\Log::error('Livewire authorization diagnostic', [
                'path' => request()->path(),
                'user_id' => auth()->id(),
                'roles' => auth()->user()?->getRoleNames()->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(7)->map(fn ($frame) => [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                ])->all(),
            ]);
        });
    })->create();
