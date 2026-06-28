<?php

use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewDraftController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/reviews');

Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::post('/reviews/{reviewRun}/fetch', [ReviewController::class, 'fetch'])->name('reviews.fetch');
Route::post('/reviews/{reviewRun}/run', [ReviewController::class, 'run'])->name('reviews.run');
Route::post('/reviews/{reviewRun}/drafts/generate', [ReviewDraftController::class, 'generate'])->name('reviews.drafts.generate');
Route::get('/reviews/{reviewRun}', [ReviewController::class, 'show'])->name('reviews.show');
