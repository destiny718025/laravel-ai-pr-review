<?php

namespace App\Contracts\GitHub;

use App\Data\GitHub\PullRequestSnapshot;

interface GitHubClient
{
    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot;

    /**
     * @return array<int, \App\Data\GitHub\PullRequestFileSnapshot>
     */
    public function listPullRequestFiles(string $owner, string $repository, int $pullRequestNumber): array;
}
