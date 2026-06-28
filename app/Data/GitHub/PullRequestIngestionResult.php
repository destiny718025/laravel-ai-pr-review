<?php

namespace App\Data\GitHub;

use App\Models\ReviewRun;

readonly class PullRequestIngestionResult
{
    private function __construct(
        private bool $successful,
        private ReviewRun $reviewRun,
        private string $message,
        private ?string $errorCode = null,
    ) {
    }

    public static function success(ReviewRun $reviewRun): self
    {
        return new self(true, $reviewRun, 'GitHub pull request data fetched.');
    }

    public static function failure(ReviewRun $reviewRun, GitHubFailure $failure): self
    {
        return new self(false, $reviewRun, $failure->message, $failure->code);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function reviewRun(): ReviewRun
    {
        return $this->reviewRun;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
