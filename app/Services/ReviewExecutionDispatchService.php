<?php

namespace App\Services;

use App\Data\AI\ReviewExecutionResult;
use App\Jobs\ExecuteReviewRunJob;
use App\Models\ReviewRun;
use App\Repositories\ReviewRunRepository;

class ReviewExecutionDispatchService
{
    public function __construct(private readonly ReviewRunRepository $reviewRuns) {}

    public function dispatch(ReviewRun|int|string $reviewRun): ReviewExecutionResult
    {
        $reviewRun = $reviewRun instanceof ReviewRun
            ? $reviewRun->loadMissing('pullRequest.repository', 'files', 'findings')
            : $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRun);

        if ($reviewRun->github_fetched_at === null) {
            return ReviewExecutionResult::failure(
                $reviewRun,
                'Fetch GitHub pull request data before running AI review.',
                'github_snapshot_missing',
            );
        }

        $reviewRun = $this->reviewRuns->queueForExecution($reviewRun);

        ExecuteReviewRunJob::dispatch($reviewRun->id)->afterCommit();

        return ReviewExecutionResult::success($reviewRun);
    }
}
