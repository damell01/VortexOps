<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Offline fallback page — served by the service worker when the network is unavailable
Route::get('/offline', function () {
    return response()->file(public_path('offline.html'));
})->name('offline');

// Public health endpoint — no auth, used by UptimeRobot / BetterUptime / Docker
Route::get('/health', HealthController::class)->name('health');

Route::middleware(['auth', 'web'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('feedback.store');
});

Route::middleware(['auth', 'web', 'throttle:6,1'])->prefix('admin/export')->name('export.')->group(function () {
    Route::get('inventory-items', [ExportController::class, 'inventoryItems'])->name('inventory-items');
    Route::get('stock-levels',    [ExportController::class, 'stockLevels'])->name('stock-levels');
    Route::get('movement-log',    [ExportController::class, 'movementLog'])->name('movement-log');
});

Route::middleware(['auth', 'web'])->get('/admin/manifest-template', [ExportController::class, 'manifestTemplate'])->name('manifest.template');
