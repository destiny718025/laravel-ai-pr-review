<?php

namespace App\Repositories;

use App\Data\GitHubPullRequestReference;
use App\Models\GitHubRepository;
use App\Models\PullRequest;

class PullRequestRepository
{
    public function findOrCreateForRepository(
        GitHubRepository $repository,
        GitHubPullRequestReference $reference,
    ): PullRequest {
        return PullRequest::firstOrCreate(
            [
                'repository_id' => $repository->id,
                'number' => $reference->pullRequestNumber,
            ],
            ['source_url' => $reference->sourceUrl],
        );
    }
}
