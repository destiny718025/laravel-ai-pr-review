<?php

use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/reviews');

Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::get('/reviews/{reviewRun}', [ReviewController::class, 'show'])->name('reviews.show');
