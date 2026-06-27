<?php

namespace App\Data\GitHub;

readonly class PullRequestSnapshot
{
    public function __construct(
        public string $title,
        public string $state,
        public string $headSha,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGitHubPayload(array $payload): self
    {
        $head = $payload['head'] ?? null;

        if (! is_array($head)) {
            throw new \UnexpectedValueException('GitHub pull request payload is missing head metadata.');
        }

        return new self(
            title: self::requiredString($payload, 'title'),
            state: self::requiredString($payload, 'state'),
            headSha: self::requiredString($head, 'sha'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException("GitHub pull request payload is missing {$key}.");
        }

        return $value;
    }
}
