<?php

namespace App\Contracts\GitHub;

use App\Data\GitHub\GitHubCommentPublicationResult;
use App\Data\GitHub\GitHubCommentPublicationTarget;
use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;

interface GitHubClient
{
    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot;

    /**
     * @return array<int, PullRequestFileSnapshot>
     */
    public function listPullRequestFiles(string $owner, string $repository, int $pullRequestNumber): array;

    public function createPullRequestReviewComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult;

    public function createPullRequestIssueComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult;
}
