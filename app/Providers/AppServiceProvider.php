<?php

namespace App\Providers;

use App\Listeners\LogAuthActivity;
use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Show;
use App\Observers\DeductionRequestObserver;
use App\Observers\PayoutObserver;
use App\Observers\ProductObserver;
use App\Observers\ShowObserver;
use App\Services\AI\OllamaClient;
use App\Services\AI\Chat\ChatService;
use App\Services\AI\Chat\ContextBuilder;
use App\Services\AI\Mapping\MappingEngine;
use App\Services\EmbeddingService;
use App\Services\ProductMatchingService;
use Filament\Support\Facades\FilamentView;
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
        $this->app->singleton(OllamaClient::class, fn () => OllamaClient::fromSettings());

        $this->app->singleton(EmbeddingService::class, fn ($app) => new EmbeddingService(
            $app->make(OllamaClient::class),
        ));

        $this->app->singleton(ProductMatchingService::class, fn ($app) => new ProductMatchingService(
            $app->make(EmbeddingService::class),
        ));

        $this->app->singleton(MappingEngine::class, fn ($app) => new MappingEngine(
            $app->make(ProductMatchingService::class),
            $app->make(OllamaClient::class),
            $app->make(EmbeddingService::class),
        ));

        $this->app->singleton(ChatService::class, fn ($app) => new ChatService(
            $app->make(\App\AI\Services\AiGateway::class),
            $app->make(ContextBuilder::class),
        ));
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
        Show::observe(ShowObserver::class);
        Product::observe(ProductObserver::class);

        FilamentView::registerRenderHook(
            'panels::body.start',
            fn (): \Illuminate\Contracts\View\View => view('filament.demo-overlay'),
        );

        $listener = new LogAuthActivity();
        Event::listen(Login::class,         [$listener, 'handleLogin']);
        Event::listen(Logout::class,        [$listener, 'handleLogout']);
        Event::listen(Failed::class,        [$listener, 'handleFailed']);
        Event::listen(PasswordReset::class, [$listener, 'handlePasswordReset']);
    }
}
