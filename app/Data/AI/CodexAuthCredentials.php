<?php

namespace App\Data\AI;

readonly class CodexAuthCredentials
{
    public function __construct(
        public string $accessToken,
        public ?string $accountId,
        public ?string $authMode,
        public ?string $lastRefresh,
    ) {}
}
