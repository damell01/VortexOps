<?php

use App\Http\Controllers\ReviewPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/',                          [ReviewPortalController::class, 'index'])->name('review.index');
Route::get('/projects/{project}',        [ReviewPortalController::class, 'project'])->name('review.project');
Route::get('/sessions/{session}',        [ReviewPortalController::class, 'session'])->name('review.session');
Route::get('/items/{item}',              [ReviewPortalController::class, 'item'])->name('review.item');
Route::post('/items/{item}/comments',    [ReviewPortalController::class, 'storeComment'])->name('review.item.comment');
Route::patch('/items/{item}/status',     [ReviewPortalController::class, 'updateStatus'])->name('review.item.status');
