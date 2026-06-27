<?php

namespace App\Data;

use App\Models\ReviewRun;

class ReviewRunCreationResult
{
    private function __construct(
        private readonly bool $successful,
        private readonly ?ReviewRun $reviewRun,
        private readonly ?string $errorCode,
        private readonly string $message,
    ) {
    }

    public static function success(ReviewRun $reviewRun): self
    {
        return new self(true, $reviewRun, null, 'Review run created.');
    }

    public static function failure(string $errorCode, string $message): self
    {
        return new self(false, null, $errorCode, $message);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function reviewRun(): ?ReviewRun
    {
        return $this->reviewRun;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function message(): string
    {
        return $this->message;
    }
}
