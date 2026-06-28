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
}
