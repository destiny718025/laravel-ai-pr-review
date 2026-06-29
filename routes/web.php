<?php

use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewDraftController;
use App\Http\Controllers\ReviewInstructionSettingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/reviews');

Route::put('/review-instructions', [ReviewInstructionSettingController::class, 'update'])->name('review-instructions.update');
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::post('/reviews/{reviewRun}/fetch', [ReviewController::class, 'fetch'])->name('reviews.fetch');
Route::post('/reviews/{reviewRun}/run', [ReviewController::class, 'run'])->name('reviews.run');
Route::post('/reviews/{reviewRun}/drafts/generate', [ReviewDraftController::class, 'generate'])->name('reviews.drafts.generate');
Route::patch('/reviews/{reviewRun}/drafts/{reviewCommentDraft}', [ReviewDraftController::class, 'update'])->name('reviews.drafts.update');
Route::post('/reviews/{reviewRun}/drafts/approve', [ReviewDraftController::class, 'approve'])->name('reviews.drafts.approve');
Route::post('/reviews/{reviewRun}/drafts/publish-approved', [ReviewDraftController::class, 'publishApproved'])->name('reviews.drafts.publish-approved');
Route::post('/reviews/{reviewRun}/drafts/retry-failed', [ReviewDraftController::class, 'retryFailed'])->name('reviews.drafts.retry-failed');
Route::post('/reviews/{reviewRun}/drafts/{reviewCommentDraft}/unapprove', [ReviewDraftController::class, 'unapprove'])->name('reviews.drafts.unapprove');
Route::get('/reviews/{reviewRun}', [ReviewController::class, 'show'])->name('reviews.show');
