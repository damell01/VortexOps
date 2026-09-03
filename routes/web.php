<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HealthController;
use App\Models\AiTask;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

    Route::get('ai-tasks/{task}/source', function (AiTask $task) {
        abort_unless($task->type === 'parse_pallet_slip', 404);

        $input = is_array($task->input) ? $task->input : [];
        $relativePath = $input['stored_path'] ?? null;
        abort_unless(is_string($relativePath) && Storage::disk('local')->exists($relativePath), 404);

        $path = Storage::disk('local')->path($relativePath);
        $name = basename((string) ($input['original_name'] ?? $input['file'] ?? basename($relativePath)));
        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename*=UTF-8''" . rawurlencode($name),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    })->name('manifest-source');
});

Route::middleware(['auth', 'web', 'throttle:6,1'])->prefix('admin/export')->name('export.')->group(function () {
    Route::get('inventory-items', [ExportController::class, 'inventoryItems'])->name('inventory-items');
    Route::get('inventory-pdf',   [ExportController::class, 'inventoryPdf'])->name('inventory-pdf');
    Route::get('inventory-analytics-pdf', [ExportController::class, 'inventoryAnalyticsPdf'])->name('inventory-analytics-pdf');
    Route::get('inventory-manual-pdf', [ExportController::class, 'inventoryManualPdf'])->name('inventory-manual-pdf');
    Route::get('stock-levels',    [ExportController::class, 'stockLevels'])->name('stock-levels');
    Route::get('movement-log',    [ExportController::class, 'movementLog'])->name('movement-log');
    Route::get('locations',       [ExportController::class, 'locations'])->name('locations');
    Route::get('shows',           [ExportController::class, 'shows'])->name('shows');
    Route::get('payouts',         [ExportController::class, 'payouts'])->name('payouts');
    Route::get('payouts/{payout}/pdf', [ExportController::class, 'payoutPdf'])->name('payout-pdf');
    Route::get('shows/{show}/pl-pdf',  [ExportController::class, 'showPlPdf'])->name('show-pl-pdf');
});

Route::middleware(['auth', 'web'])->get('/admin/manifest-template', [ExportController::class, 'manifestTemplate'])->name('manifest.template');
