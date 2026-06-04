<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth', 'web'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});

Route::middleware(['auth', 'web'])->prefix('admin/export')->name('export.')->group(function () {
    Route::get('inventory-items', [ExportController::class, 'inventoryItems'])->name('inventory-items');
    Route::get('stock-levels',    [ExportController::class, 'stockLevels'])->name('stock-levels');
    Route::get('movement-log',    [ExportController::class, 'movementLog'])->name('movement-log');
});
