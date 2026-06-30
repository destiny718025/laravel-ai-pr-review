<?php

namespace App\Http\Controllers;

use App\Data\ReviewCommentPublishingResult;
use App\Services\ReviewCommentPublishingService;
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

    public function publishApproved(
        int|string $reviewRun,
        ReviewCommentPublishingService $reviewCommentPublishingService,
    ): RedirectResponse {
        $result = $reviewCommentPublishingService->publishApproved($reviewRun);

        return $this->redirectWithPublicationResult($result);
    }

    public function retryFailed(
        int|string $reviewRun,
        ReviewCommentPublishingService $reviewCommentPublishingService,
    ): RedirectResponse {
        $result = $reviewCommentPublishingService->retryFailed($reviewRun);

        return $this->redirectWithPublicationResult($result);
    }

    private function redirectWithPublicationResult(ReviewCommentPublishingResult $result): RedirectResponse
    {
        $redirect = redirect()->route('reviews.show', $result->reviewRun);

        if ($result->publishedCount === 0 && $result->failedCount > 0) {
            return $redirect
                ->with('service_error_code', $result->mode === 'publish-approved' ? 'publish_failed' : 'retry_failed')
                ->with('service_error_message', $this->publicationFailureMessage($result));
        }

        return $redirect->with('status', $this->publicationStatusMessage($result));
    }

    private function publicationStatusMessage(ReviewCommentPublishingResult $result): string
    {
        if ($result->mode === 'retry-failed') {
            return "Retried {$result->attemptedCount} failed comment drafts. {$result->publishedCount} published, {$result->failedCount} failed.";
        }

        return "Published {$result->publishedCount} approved comment drafts. {$result->failedCount} failed.";
    }

    private function publicationFailureMessage(ReviewCommentPublishingResult $result): string
    {
        if ($result->mode === 'retry-failed') {
            return "Retry finished with {$result->failedCount} failed comment drafts and no successful publications.";
        }

        return "Publishing finished with {$result->failedCount} failed approved comment drafts and no successful publications.";
    }
}
