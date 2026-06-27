<?php

namespace App\Repositories;

use App\Data\GitHubPullRequestReference;
use App\Models\GitHubRepository;

class GitHubRepositoryRepository
{
    public function findOrCreateFromReference(GitHubPullRequestReference $reference): GitHubRepository
    {
        return GitHubRepository::firstOrCreate(
            ['full_name' => $reference->fullName()],
            [
                'owner' => $reference->owner,
                'name' => $reference->repositoryName,
            ],
        );
    }
}
