<?php

namespace App\Services;

use App\Models\ReviewCommentDraft;
use App\Models\ReviewRun;
use App\Repositories\ReviewCommentDraftRepository;
use App\Repositories\ReviewFindingRepository;
use App\Repositories\ReviewRunRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function updateDraftBody(int|string $reviewRunId, int|string $draftId, string $body): void
    {
        $reviewRun = $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRunId);

        DB::transaction(function () use ($reviewRun, $draftId, $body): void {
            $draft = $this->drafts->findForReviewRunOrFail($reviewRun, $draftId);

            if (! $draft->status->isDraft()) {
                throw new AuthorizationException('Only draft comment drafts can be edited.');
            }

            $this->drafts->updateBody($draft, $body);
        });
    }

    /**
     * @param  array<int, int|string>  $draftIds
     */
    public function approveDrafts(int|string $reviewRunId, array $draftIds): int
    {
        $reviewRun = $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRunId);
        $draftIds = $this->normalizeDraftIds($draftIds);

        if ($draftIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($reviewRun, $draftIds): int {
            $drafts = $this->drafts->findManyForReviewRun($reviewRun, $draftIds);

            if ($drafts->count() !== count($draftIds)) {
                throw (new ModelNotFoundException)->setModel(ReviewCommentDraft::class, $draftIds);
            }

            if ($drafts->contains(fn ($draft): bool => ! $draft->status->isDraft())) {
                throw new AuthorizationException('Only draft comment drafts can be approved.');
            }

            return $this->drafts->markApprovedForIds($reviewRun, $draftIds);
        });
    }

    public function unapproveDraft(int|string $reviewRunId, int|string $draftId): void
    {
        $reviewRun = $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRunId);

        DB::transaction(function () use ($reviewRun, $draftId): void {
            $draft = $this->drafts->findForReviewRunOrFail($reviewRun, $draftId);

            if (! $draft->status->isApproved()) {
                throw new AuthorizationException('Only approved comment drafts can be returned to draft.');
            }

            $this->drafts->markDraft($draft);
        });
    }

    private function sourceFileSha(ReviewRun $reviewRun, string $filePath): ?string
    {
        return $reviewRun->files
            ->firstWhere('filename', $filePath)
            ?->sha;
    }

    /**
     * @param  array<int, int|string>  $draftIds
     * @return array<int, int>
     */
    private function normalizeDraftIds(array $draftIds): array
    {
        return array_values(array_unique(array_map(
            fn (int|string $draftId): int => (int) $draftId,
            $draftIds,
        )));
    }
}
