<?php

namespace App\Support;

use App\Notifications\VortexAlert;
use App\Services\Notifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells the owner when the app crashes: a page, a button or a background job
 * failing with an error nobody would otherwise see until they opened it.
 *
 * Everyday outcomes are not crashes (a 404, a failed form, an expired form, a
 * permission refusal) and are never sent. The same error (class, file, line)
 * is sent at most once an hour, so a broken page cannot flood anyone.
 */
final class ErrorAlert
{
    public const THROTTLE_MINUTES = 60;

    private static bool $sending = false;

    /** @var list<class-string> */
    private const IGNORED = [
        \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Livewire\Exceptions\ComponentNotFoundException::class,
    ];

    public static function enabled(): bool
    {
        $setting = config('app.error_alerts');

        return $setting === null ? app()->environment('production') : filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public static function shouldSend(Throwable $e): bool
    {
        foreach (self::IGNORED as $class) {
            if ($e instanceof $class) return false;
        }
        // A deploy rebuilds bootstrap/cache; a scheduler run that starts in that
        // instant cannot read the old file. The next minute's run is fine.
        if (str_contains($e->getMessage(), 'bootstrap/cache/') && str_contains($e->getMessage(), 'Failed to open stream')) return false;

        return true;
    }

    public static function fingerprint(Throwable $e): string
    {
        return sha1(get_class($e) . '|' . $e->getFile() . '|' . $e->getLine());
    }

    /** Sends the alert; returns whether one went out. Never throws. */
    public static function report(Throwable $e): bool
    {
        if (self::$sending || ! self::enabled() || ! self::shouldSend($e)) return false;

        self::$sending = true;
        try {
            if (! Cache::add('error-alert:' . self::fingerprint($e), 1, now()->addMinutes(self::THROTTLE_MINUTES))) return false;

            $request = app()->bound('request') ? request() : null;
            $where = $request?->route() !== null
                ? $request->method() . ' /' . ltrim($request->path(), '/')
                : 'Background: ' . implode(' ', array_slice($_SERVER['argv'] ?? ['artisan'], 1, 3));
            $user = auth()->user();
            $file = Str::after($e->getFile(), base_path() . DIRECTORY_SEPARATOR);

            Notifier::send('app_error', new VortexAlert(
                event: 'app_error',
                title: 'Error: ' . Str::limit(class_basename($e) . ' on ' . $where, 110),
                body: Str::limit($e->getMessage() ?: 'No message', 300)
                    . "\n{$file}:{$e->getLine()}"
                    . ($user ? "\nSigned in: {$user->name} (#{$user->id})" : '')
                    . "\n" . config('app.name') . ' · ' . app()->environment() . ' · ' . now()->toDateTimeString(),
                tone: 'danger',
                links: [],
                icon: 'heroicon-o-bug-ant',
            ));

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            self::$sending = false;
        }
    }
}
