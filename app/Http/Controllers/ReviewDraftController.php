<?php

namespace App\Http\Controllers;

use App\Services\ReviewDraftService;
use Illuminate\Http\RedirectResponse;

class ReviewDraftController extends Controller
{
    public function generate(int|string $reviewRun, ReviewDraftService $reviewDraftService): RedirectResponse
    {
        $created = $reviewDraftService->generateMissingDraftsForReviewRun($reviewRun);

        return redirect()
            ->route('reviews.show', $reviewRun)
            ->with('status', "Generated {$created} comment drafts.");
    }
}
