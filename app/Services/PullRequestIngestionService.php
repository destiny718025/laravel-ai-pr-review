<?php

namespace App\Services;

use App\Contracts\GitHub\GitHubClient;
use App\Data\GitHub\PullRequestIngestionResult;
use App\Models\ReviewRun;
use App\Repositories\ReviewRunRepository;
use App\Services\GitHub\GitHubFailureMapper;

class PullRequestIngestionService
{
    public function __construct(
        private readonly GitHubClient $githubClient,
        private readonly ReviewRunRepository $reviewRuns,
        private readonly GitHubFailureMapper $failureMapper,
    ) {
    }

    public function fetch(ReviewRun|int|string $reviewRun): PullRequestIngestionResult
    {
        $reviewRun = $reviewRun instanceof ReviewRun
            ? $reviewRun->loadMissing('pullRequest.repository')
            : $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRun);

        $pullRequest = $reviewRun->pullRequest;
        $repository = $pullRequest->repository;

        try {
            $snapshot = $this->githubClient->getPullRequest(
                $repository->owner,
                $repository->name,
                $pullRequest->number,
            );

            $files = $this->githubClient->listPullRequestFiles(
                $repository->owner,
                $repository->name,
                $pullRequest->number,
            );

            $reviewRun = $this->reviewRuns->storeGitHubSnapshot($reviewRun, $snapshot, $files);

            return PullRequestIngestionResult::success($reviewRun);
        } catch (\Throwable $throwable) {
            $failure = $this->failureMapper->map($throwable);
            $reviewRun = $this->reviewRuns->markGitHubFetchFailed($reviewRun, $failure->message);

            return PullRequestIngestionResult::failure($reviewRun, $failure);
        }
    }
}
