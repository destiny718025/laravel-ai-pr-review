<?php

namespace App\Data\AI;

use App\Models\ReviewRun;

readonly class ReviewExecutionResult
{
    private function __construct(
        private bool $successful,
        private ReviewRun $reviewRun,
        private string $message,
        private ?string $errorCode = null,
    ) {}

    public static function success(ReviewRun $reviewRun): self
    {
        return new self(true, $reviewRun, 'AI review queued.');
    }

    public static function failure(ReviewRun $reviewRun, string $message, string $errorCode): self
    {
        return new self(false, $reviewRun, $message, $errorCode);
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
