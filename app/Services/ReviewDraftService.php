<?php

namespace App\Services;

use App\Models\ReviewRun;
use App\Repositories\ReviewCommentDraftRepository;
use App\Repositories\ReviewFindingRepository;
use App\Repositories\ReviewRunRepository;
use Illuminate\Support\Facades\DB;

class ReviewDraftService
{
    public function __construct(
        private readonly ReviewRunRepository $reviewRuns,
        private readonly ReviewFindingRepository $findings,
        private readonly ReviewCommentDraftRepository $drafts,
    ) {}

    public function generateMissingDraftsForReviewRun(int|string $reviewRunId): int
    {
        $reviewRun = $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRunId);

        return DB::transaction(function () use ($reviewRun): int {
            $reviewRun->loadMissing('files');

            $created = 0;
            foreach ($this->findings->currentWithoutDrafts($reviewRun) as $finding) {
                $this->drafts->createFromFinding(
                    reviewRun: $reviewRun,
                    finding: $finding,
                    sourceFileSha: $this->sourceFileSha($reviewRun, $finding->file_path),
                );

                $created++;
            }

            return $created;
        });
    }

    private function sourceFileSha(ReviewRun $reviewRun, string $filePath): ?string
    {
        return $reviewRun->files
            ->firstWhere('filename', $filePath)
            ?->sha;
    }
}
