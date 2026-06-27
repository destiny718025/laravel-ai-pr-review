<?php

namespace App\Data;

class GitHubPullRequestReference
{
    public function __construct(
        public readonly string $owner,
        public readonly string $repositoryName,
        public readonly int $pullRequestNumber,
        public readonly string $sourceUrl,
    ) {
    }

    public function fullName(): string
    {
        return strtolower($this->owner.'/'.$this->repositoryName);
    }
}
