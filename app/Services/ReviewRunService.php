<?php

namespace App\Services;

use App\Data\GitHubPullRequestReference;
use App\Data\ReviewRunCreationResult;
use App\Repositories\GitHubRepositoryRepository;
use App\Repositories\PullRequestRepository;
use App\Repositories\ReviewRunRepository;
use App\Services\GitHub\GitHubPullRequestUrlParser;

class ReviewRunService
{
    public function __construct(
        private readonly GitHubPullRequestUrlParser $parser,
        private readonly GitHubRepositoryRepository $repositories,
        private readonly PullRequestRepository $pullRequests,
        private readonly ReviewRunRepository $reviewRuns,
    ) {
    }

    public function createFromPullRequestUrl(string $url): ReviewRunCreationResult
    {
        $reference = $this->parser->parse($url);

        if (! $reference instanceof GitHubPullRequestReference) {
            return ReviewRunCreationResult::failure(
                $reference,
                $this->messageForErrorCode($reference),
            );
        }

        $repository = $this->repositories->findOrCreateFromReference($reference);
        $pullRequest = $this->pullRequests->findOrCreateForRepository($repository, $reference);
        $reviewRun = $this->reviewRuns->createPendingForPullRequest($pullRequest);

        return ReviewRunCreationResult::success($reviewRun->load('pullRequest.repository'));
    }

    private function messageForErrorCode(string $errorCode): string
    {
        return match ($errorCode) {
            'invalid_url' => 'Enter a valid HTTPS GitHub pull request URL.',
            'not_github_pr_url' => 'Enter a GitHub pull request URL from github.com.',
            'missing_pr_number' => 'Enter a GitHub pull request URL with a valid pull request number.',
            default => 'The pull request URL could not be reviewed.',
        };
    }
}
