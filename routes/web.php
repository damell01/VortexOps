<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HealthController;
use App\Models\AiTask;
use App\Models\FulfillmentPackage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('auth')->group(function () {
    Route::get('/account/change-password', [\App\Http\Controllers\RequiredPasswordChangeController::class, 'show'])->name('account.password.edit');
    Route::post('/account/change-password', [\App\Http\Controllers\RequiredPasswordChangeController::class, 'update'])->middleware('throttle:6,1')->name('account.password.update');
    Route::post('/impersonation/stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])->name('impersonation.stop');
});

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/offline', function () {
    return response()->file(public_path('offline.html'));
})->name('offline');

Route::get('/health', HealthController::class)->name('health');

// Open copies of the app poll this to notice a deploy (resources/js/app-shell.js).
// JSON, so the service worker passes it straight to the network.
Route::get('app-version', fn () => response()->json(['version' => \App\Support\AppVersion::current()])
    ->header('Cache-Control', 'no-store'))->name('app-version');

Route::middleware(['auth', 'web'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('inventory-guide/images/{image}', \App\Http\Controllers\InventoryGuideImageController::class)->name('inventory-guide.image');
    Route::get('guides/{guide}', \App\Http\Controllers\GuideDocumentController::class)->where('guide', 'streamer|admin|inventory')->name('guides.show');

    Route::post('feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('feedback.store');

    Route::post('timezone', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        if ($request->user()->timezone !== $data['timezone']) {
            $request->user()->forceFill(['timezone' => $data['timezone']])->save();
        }

        return response()->noContent();
    })->middleware('throttle:6,1')->name('timezone.update');

    // A button that was pressed but sent nothing (resources/js/action-feedback.js):
    // the browser blocked it, or a script error ate the click. Logged so the
    // cause can be read in Log Viewer instead of guessed at.
    Route::post('client-diagnostic', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'what' => ['required', 'string', 'max:200'],
            'detail' => ['nullable', 'string', 'max:2000'],
            'page' => ['nullable', 'string', 'max:500'],
        ]);
        \Illuminate\Support\Facades\Log::warning('Client: ' . $data['what'], [
            'detail' => $data['detail'] ?? null,
            'page' => $data['page'] ?? null,
            'user' => $request->user()->id,
            'agent' => mb_substr((string) $request->userAgent(), 0, 300),
        ]);

        return response()->noContent();
    })->middleware('throttle:20,1')->name('client-diagnostic');

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

    Route::get('fulfillment-box/{packageCode}', function (string $packageCode) {
        $package = FulfillmentPackage::query()
            ->where('package_code', $packageCode)
            ->with([
                'show.streamers',
                'shipment',
                'packedBy',
                'items.streamerLogItem.inventoryItem',
            ])
            ->firstOrFail();

        $user = auth()->user();
        $show = $package->show;
        $allowed = $user && $show && (
            $user->isAdmin()
            || $user->isOwner()
            || $user->isFulfillmentAdmin()
            || ($user->isFulfillment() && $show->fulfillmentUsers()->where('users.id', $user->id)->exists())
        );
        abort_unless($allowed, 403);

        return view('fulfillment.box-verification', compact('package'));
    })->name('fulfillment-box.verify');
});

Route::middleware(['auth', 'web', 'throttle:6,1'])->prefix('admin/export')->name('export.')->group(function () {
    Route::get('inventory-prices', [ExportController::class, 'inventoryPriceList'])->name('inventory-prices');
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

// The words behind the "?" help buttons (resources/js/help.js), loaded on first click.
Route::middleware(['auth', 'web'])->get('/admin/help-topics.json', function () {
    return response()->json(\App\Support\HelpTopics::all())->header('Cache-Control', 'private, max-age=300');
})->name('help.topics');

// The newest unread "A wild report appeared!" alert, for the Pokémon toast
// (resources/js/delight.js asks when the notification bell's count goes up).
Route::middleware(['auth', 'web'])->get('/admin/wild-report.json', function () {
    $alert = auth()->user()->unreadNotifications()->where('created_at', '>=', now()->subMinutes(15))->latest()->limit(10)->get()
        ->first(fn ($n) => ($n->data['vx']['event'] ?? null) === 'report_filed');
    if (! $alert) {
        return response()->json(null)->header('Cache-Control', 'no-store');
    }
    $url = collect($alert->data['actions'] ?? [])->pluck('url')->filter()->first();

    return response()->json([
        'id' => $alert->id, 'title' => $alert->data['title'] ?? 'A wild report appeared!', 'body' => $alert->data['body'] ?? '',
        'species' => \App\Support\Pokedex::exists($alert->data['vx']['species'] ?? null) ? $alert->data['vx']['species'] : null,
        'shiny' => (bool) ($alert->data['vx']['shiny'] ?? false), 'url' => $url,
    ])->header('Cache-Control', 'no-store');
})->name('wild-report');

// Private browser statements and matching printable PDFs.
Route::middleware(['web', 'auth'])->prefix('admin/financial-reports')->name('financial-reports.')->group(function () {
    Route::get('pay-runs/{batch}', [\App\Http\Controllers\FinancialReportController::class, 'run'])->name('pay-run');
    Route::get('business-statement', [\App\Http\Controllers\FinancialReportController::class, 'statement'])->name('statement');
});

Route::middleware(['web', 'auth', 'throttle:30,1'])->prefix('admin/push')->name('push.')->group(function () {
    Route::get('config', [\App\Http\Controllers\WebPushController::class, 'config'])->name('config');
    Route::post('status', [\App\Http\Controllers\WebPushController::class, 'status'])->name('status');
    Route::post('subscriptions', [\App\Http\Controllers\WebPushController::class, 'store'])->name('store');
    Route::delete('subscriptions', [\App\Http\Controllers\WebPushController::class, 'destroy'])->name('destroy');
    Route::post('test', [\App\Http\Controllers\WebPushController::class, 'test'])->middleware('throttle:3,1,push-test:')->name('test');
});

// Communications files have no public storage URL; every download rechecks preview access and membership.
Route::middleware(['web', 'auth', 'throttle:60,1'])->get('/admin/communications/attachments/{attachment}', \App\Http\Controllers\TeamMessageAttachmentController::class)->whereNumber('attachment')->name('communications.attachment');
