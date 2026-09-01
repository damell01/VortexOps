<?php

namespace App\Providers;

use App\Http\Controllers\InventoryScannerBarcodeController;
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
use App\Support\TableFilterPresentation;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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

        // Every table in the panel, not just the resources — relation managers
        // and the custom pages that build their own tables go through the same
        // Table::make(), so they pick this up as well.
        Table::configureUsing(fn (Table $table) => TableFilterPresentation::apply($table));

        // Scanner-only helper endpoints. They intentionally live behind the web
        // auth/session middleware and the controller also verifies admin/owner
        // access before reading or changing product identities.
        Route::middleware('web')->prefix('inventory-scanner-api')->group(function (): void {
            Route::get('/items', [InventoryScannerBarcodeController::class, 'search']);
            Route::post('/barcodes/attach', [InventoryScannerBarcodeController::class, 'attach']);
            Route::post('/items/create', [InventoryScannerBarcodeController::class, 'create']);
            Route::get('/items/{item}/barcodes', [InventoryScannerBarcodeController::class, 'listForItem']);
            Route::delete('/barcodes/{identity}', [InventoryScannerBarcodeController::class, 'remove']);
        });

        FilamentView::registerRenderHook(
            'panels::body.start',
            fn (): \Illuminate\Contracts\View\View => view('filament.demo-overlay'),
        );

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): \Illuminate\Contracts\View\View => view('filament.mobile-polish'),
        );

        $listener = new LogAuthActivity();
        Event::listen(Login::class,         [$listener, 'handleLogin']);
        Event::listen(Logout::class,        [$listener, 'handleLogout']);
        Event::listen(Failed::class,        [$listener, 'handleFailed']);
        Event::listen(PasswordReset::class, [$listener, 'handlePasswordReset']);
    }
}
