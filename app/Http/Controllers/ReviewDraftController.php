<?php

namespace App\Http\Controllers;

use App\Services\ReviewDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewDraftController extends Controller
{
    public function generate(int|string $reviewRun, ReviewDraftService $reviewDraftService): RedirectResponse
    {
        $created = $reviewDraftService->generateMissingDraftsForReviewRun($reviewRun);

        return redirect()
            ->route('reviews.show', $reviewRun)
            ->with('status', "Generated {$created} comment drafts.");
    }

    public function update(
        Request $request,
        int|string $reviewRun,
        int|string $reviewCommentDraft,
        ReviewDraftService $reviewDraftService,
    ): RedirectResponse {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $reviewDraftService->updateDraftBody($reviewRun, $reviewCommentDraft, $validated['body']);

        return redirect()
            ->route('reviews.show', $reviewRun)
            ->with('status', 'Comment draft updated.');
    }

    public function approve(
        Request $request,
        int|string $reviewRun,
        ReviewDraftService $reviewDraftService,
    ): RedirectResponse {
        $validated = $request->validate([
            'draft_ids' => ['array'],
            'draft_ids.*' => ['integer'],
        ]);

        $approved = $reviewDraftService->approveDrafts($reviewRun, $validated['draft_ids'] ?? []);

        return redirect()
            ->route('reviews.show', $reviewRun)
            ->with('status', "Approved {$approved} comment drafts.");
    }

    public function unapprove(
        int|string $reviewRun,
        int|string $reviewCommentDraft,
        ReviewDraftService $reviewDraftService,
    ): RedirectResponse {
        $reviewDraftService->unapproveDraft($reviewRun, $reviewCommentDraft);

        return redirect()
            ->route('reviews.show', $reviewRun)
            ->with('status', 'Comment draft returned to draft.');
    }
}
