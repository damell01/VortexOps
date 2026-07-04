<?php

namespace App\Providers;

use App\Listeners\LogAuthActivity;
use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Observers\DeductionRequestObserver;
use App\Observers\PayoutObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Admins and the owner bypass all Shield-generated policy checks.
        // super_admin gets the same treatment via filament-shield's gate intercept.
        Gate::before(function ($user, $ability) {
            if ($user->isAdmin() || $user->isOwner()) {
                return true;
            }
        });

        Payout::observe(PayoutObserver::class);
        DeductionRequest::observe(DeductionRequestObserver::class);

        $listener = new LogAuthActivity();
        Event::listen(Login::class,         [$listener, 'handleLogin']);
        Event::listen(Logout::class,        [$listener, 'handleLogout']);
        Event::listen(Failed::class,        [$listener, 'handleFailed']);
        Event::listen(PasswordReset::class, [$listener, 'handlePasswordReset']);
    }
}
