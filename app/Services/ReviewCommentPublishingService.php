<?php

namespace App\Services;

use App\Contracts\GitHub\GitHubClient;
use App\Data\GitHub\GitHubCommentPublicationResult;
use App\Data\GitHub\GitHubCommentPublicationTarget;
use App\Data\ReviewCommentPublishingResult;
use App\Models\ReviewCommentDraft;
use App\Models\ReviewRun;
use App\Repositories\ReviewCommentDraftRepository;
use App\Repositories\ReviewRunRepository;
use App\Services\GitHub\GitHubFailureMapper;

class ReviewCommentPublishingService
{
    public function __construct(
        private readonly GitHubClient $githubClient,
        private readonly ReviewRunRepository $reviewRuns,
        private readonly ReviewCommentDraftRepository $drafts,
        private readonly GitHubFailureMapper $failureMapper,
    ) {}

    public function publishApproved(ReviewRun|int|string $reviewRun): ReviewCommentPublishingResult
    {
        return $this->publish($reviewRun, 'publish-approved');
    }

    public function retryFailed(ReviewRun|int|string $reviewRun): ReviewCommentPublishingResult
    {
        return $this->publish($reviewRun, 'retry-failed');
    }

    private function publish(ReviewRun|int|string $reviewRun, string $mode): ReviewCommentPublishingResult
    {
        $reviewRun = $reviewRun instanceof ReviewRun
            ? $reviewRun->loadMissing('pullRequest.repository')
            : $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRun);

        $drafts = $mode === 'publish-approved'
            ? $this->drafts->approvedForReviewRun($reviewRun)
            : $this->drafts->failedForReviewRun($reviewRun);

        $publishedCount = 0;
        $failedCount = 0;

        foreach ($drafts as $draft) {
            try {
                $result = $this->publishDraft($reviewRun, $draft);
                $this->drafts->markPosted($draft, $result);
                $publishedCount++;
            } catch (\Throwable $throwable) {
                $failure = $this->failureMapper->mapPublication($throwable);
                $this->drafts->markPublicationFailed($draft, $failure);
                $failedCount++;
            }
        }

        return new ReviewCommentPublishingResult(
            reviewRun: $reviewRun,
            mode: $mode,
            attemptedCount: $drafts->count(),
            publishedCount: $publishedCount,
            failedCount: $failedCount,
        );
    }

    private function publishDraft(ReviewRun $reviewRun, ReviewCommentDraft $draft): GitHubCommentPublicationResult
    {
        $pullRequest = $reviewRun->pullRequest;
        $repository = $pullRequest->repository;
        $hasLineLevelTarget = $draft->hasSufficientLineLevelTarget();

        $target = new GitHubCommentPublicationTarget(
            owner: $repository->owner,
            repository: $repository->name,
            pullRequestNumber: $pullRequest->number,
            body: $draft->body,
            path: $hasLineLevelTarget ? $draft->file_path : null,
            line: $hasLineLevelTarget ? $draft->lineNumber() : null,
            commitSha: $hasLineLevelTarget ? $draft->github_head_sha : null,
        );

        if ($hasLineLevelTarget) {
            return $this->githubClient->createPullRequestReviewComment($target);
        }

        return $this->githubClient->createPullRequestIssueComment($target);
    }
}
