<?php

namespace App\Data\AI;

readonly class AIReviewFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
