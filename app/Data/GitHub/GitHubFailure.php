<?php

namespace App\Data\GitHub;

readonly class GitHubFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {
    }
}
