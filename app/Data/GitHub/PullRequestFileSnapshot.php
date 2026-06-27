<?php

namespace App\Data\GitHub;

readonly class PullRequestFileSnapshot
{
    public function __construct(
        public string $filename,
        public string $patch,
        public string $sha,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGitHubPayload(array $payload): self
    {
        return new self(
            filename: self::requiredString($payload, 'filename'),
            patch: self::requiredString($payload, 'patch'),
            sha: self::requiredString($payload, 'sha'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException("GitHub pull request file payload is missing {$key}.");
        }

        return $value;
    }
}
