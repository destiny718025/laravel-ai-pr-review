<?php

namespace App\Data\AI;

readonly class AIReviewRequest
{
    /**
     * @param  array<int, array{filename: string, patch: string, sha: string}>  $changedFiles
     */
    public function __construct(
        public string $repositoryFullName,
        public int $pullRequestNumber,
        public string $sourceUrl,
        public string $headSha,
        public string $title,
        public array $changedFiles,
        public string $instructions,
    ) {}
}
