<?php

use App\Http\Controllers\Api\ShowImportController;
use App\Http\Controllers\Api\WhatnotCollectorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::post('/shows/import', [ShowImportController::class, 'import']);
    Route::get('/shows/{show}', [ShowImportController::class, 'show']);
    Route::get('/channels', [ShowImportController::class, 'channels']);
    Route::get('/streamers', [ShowImportController::class, 'streamers']);

    Route::prefix('whatnot/collector')->group(function () {
        Route::get('/bootstrap', [WhatnotCollectorController::class, 'bootstrap']);
        Route::post('/import', [WhatnotCollectorController::class, 'import']);
        Route::get('/latest', [WhatnotCollectorController::class, 'latest']);
    });
});
