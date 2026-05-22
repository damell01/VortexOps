<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', 'web'])->prefix('admin/export')->name('export.')->group(function () {
    Route::get('inventory-items', [ExportController::class, 'inventoryItems'])->name('inventory-items');
    Route::get('stock-levels',    [ExportController::class, 'stockLevels'])->name('stock-levels');
    Route::get('movement-log',    [ExportController::class, 'movementLog'])->name('movement-log');
});

Route::middleware(['auth', 'web', 'module:reviews'])->prefix('admin/review')->name('review.')->group(function () {
    Route::get('sessions',                              [ReviewController::class, 'sessions'])->name('sessions');
    Route::post('sessions',                             [ReviewController::class, 'storeSession'])->name('sessions.store');
    Route::get('items',                                 [ReviewController::class, 'items'])->name('items');
    Route::post('items',                                [ReviewController::class, 'storeItem'])->name('items.store');
    Route::patch('items/{item}',                        [ReviewController::class, 'updateItem'])->name('items.update');
    Route::delete('items/{item}',                       [ReviewController::class, 'deleteItem'])->name('items.delete');
    Route::post('items/{item}/comments',                [ReviewController::class, 'storeComment'])->name('items.comments.store');
});
