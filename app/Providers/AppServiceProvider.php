<?php

namespace App\Providers;

use App\Listeners\LogAuthActivity;
use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Show;
use App\Observers\DeductionRequestObserver;
use App\Observers\PayoutObserver;
use App\Observers\ProductObserver;
use App\Observers\ShipmentObserver;
use App\Observers\ShowObserver;
use App\Services\AI\OllamaClient;
use App\Services\AI\Mapping\MappingEngine;
use App\Services\EmbeddingService;
use App\Services\InventoryVelocityService;
use App\Services\PackingSlipAnalyzerService;
use App\Services\ProductMatchingService;
use App\Services\ReceivingReportService;
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

        $this->app->singleton(PackingSlipAnalyzerService::class, fn ($app) => new PackingSlipAnalyzerService(
            $app->make(OllamaClient::class),
        ));

        $this->app->singleton(ReceivingReportService::class);
        $this->app->singleton(InventoryVelocityService::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Gate::before(function ($user, $ability) {
            if ($user->isAdmin() || $user->isOwner()) {
                return true;
            }
        });

        Payout::observe(PayoutObserver::class);
        DeductionRequest::observe(DeductionRequestObserver::class);
        Show::observe(ShowObserver::class);
        Shipment::observe(ShipmentObserver::class);
        Product::observe(ProductObserver::class);

        FilamentView::registerRenderHook(
            'panels::body.start',
            fn (): \Illuminate\Contracts\View\View => view('filament.demo-overlay'),
        );

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): \Illuminate\Contracts\View\View => view('filament.mobile-polish'),
        );

        // One lightweight tour layer for all operational pages. Individual pages
        // expose small data-vx-tour anchors, while the component owns onboarding,
        // mobile-safe bottom-sheet instructions, highlighting, and restart state.
        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): \Illuminate\Contracts\View\View => view('filament.page-tour'),
        );

        $listener = new LogAuthActivity();
        Event::listen(Login::class,         [$listener, 'handleLogin']);
        Event::listen(Logout::class,        [$listener, 'handleLogout']);
        Event::listen(Failed::class,        [$listener, 'handleFailed']);
        Event::listen(PasswordReset::class, [$listener, 'handlePasswordReset']);
    }
}
