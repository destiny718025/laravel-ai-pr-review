<?php

namespace App\Repositories;

use App\Enums\ReviewCommentDraftStatus;
use App\Models\ReviewCommentDraft;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Database\Eloquent\Collection;

class ReviewCommentDraftRepository
{
    public function createFromFinding(
        ReviewRun $reviewRun,
        ReviewFinding $finding,
        ?string $body = null,
        ?string $sourceFileSha = null,
        ReviewCommentDraftStatus $status = ReviewCommentDraftStatus::Draft,
    ): ReviewCommentDraft {
        return ReviewCommentDraft::query()->create([
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $finding->id,
            'status' => $status,
            'body' => $body ?? $finding->suggested_comment_text,
            'file_path' => $finding->file_path,
            'line_reference' => $finding->line_reference,
            'github_head_sha' => (string) $reviewRun->github_head_sha,
            'source_file_sha' => $sourceFileSha,
            'stale_at' => null,
        ]);
    }

    /**
     * @return Collection<int, ReviewCommentDraft>
     */
    public function loadForReviewRunWithSourceFindings(ReviewRun $reviewRun): Collection
    {
        return ReviewCommentDraft::query()
            ->with('sourceFinding')
            ->where('review_run_id', $reviewRun->id)
            ->orderBy('id')
            ->get();
    }

    public function markStaleForReviewRun(ReviewRun $reviewRun): int
    {
        return ReviewCommentDraft::query()
            ->where('review_run_id', $reviewRun->id)
            ->whereNull('stale_at')
            ->update([
                'stale_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function findForReviewRunOrFail(ReviewRun $reviewRun, int|string $draftId): ReviewCommentDraft
    {
        return ReviewCommentDraft::query()
            ->where('review_run_id', $reviewRun->id)
            ->whereKey($draftId)
            ->firstOrFail();
    }

    /**
     * @param  array<int, int>  $draftIds
     * @return Collection<int, ReviewCommentDraft>
     */
    public function findManyForReviewRun(ReviewRun $reviewRun, array $draftIds): Collection
    {
        return ReviewCommentDraft::query()
            ->where('review_run_id', $reviewRun->id)
            ->whereIn('id', $draftIds)
            ->orderBy('id')
            ->get();
    }

    public function updateBody(ReviewCommentDraft $draft, string $body): ReviewCommentDraft
    {
        $draft->forceFill(['body' => $body])->save();

        return $draft->refresh();
    }

    /**
     * @param  array<int, int>  $draftIds
     */
    public function markApprovedForIds(ReviewRun $reviewRun, array $draftIds): int
    {
        return ReviewCommentDraft::query()
            ->where('review_run_id', $reviewRun->id)
            ->whereIn('id', $draftIds)
            ->update([
                'status' => ReviewCommentDraftStatus::Approved,
                'updated_at' => now(),
            ]);
    }

    public function markDraft(ReviewCommentDraft $draft): ReviewCommentDraft
    {
        $draft->forceFill(['status' => ReviewCommentDraftStatus::Draft])->save();

        return $draft->refresh();
    }
}
