<?php

namespace App\Data\GitHub;

readonly class GitHubCommentPublicationTarget
{
    public function __construct(
        public string $owner,
        public string $repository,
        public int $pullRequestNumber,
        public string $body,
        public ?string $path = null,
        public ?int $line = null,
        public ?string $commitSha = null,
    ) {}

    /**
     * @return array{body: string, commit_id: string, path: string, line: int, side: string}
     */
    public function toPullRequestReviewCommentPayload(): array
    {
        if ($this->path === null || $this->path === '') {
            throw new \UnexpectedValueException('GitHub review comment target is missing path.');
        }

        if ($this->line === null || $this->line < 1) {
            throw new \UnexpectedValueException('GitHub review comment target is missing line.');
        }

        if ($this->commitSha === null || $this->commitSha === '') {
            throw new \UnexpectedValueException('GitHub review comment target is missing commit SHA.');
        }

        return [
            'body' => $this->body,
            'commit_id' => $this->commitSha,
            'path' => $this->path,
            'line' => $this->line,
            'side' => 'RIGHT',
        ];
    }

    /**
     * @return array{body: string}
     */
    public function toIssueCommentPayload(): array
    {
        return [
            'body' => $this->body,
        ];
    }
}
