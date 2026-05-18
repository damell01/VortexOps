<?php

use App\Http\Controllers\Api\ShowImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function () {
    Route::post('/shows/import', [ShowImportController::class, 'import']);
    Route::get('/shows/{show}', [ShowImportController::class, 'show']);
    Route::get('/channels', [ShowImportController::class, 'channels']);
    Route::get('/streamers', [ShowImportController::class, 'streamers']);
});
