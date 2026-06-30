<?php

namespace App\Data\GitHub;

use Carbon\CarbonImmutable;

readonly class GitHubCommentPublicationResult
{
    public function __construct(
        public string $id,
        public string $htmlUrl,
        public CarbonImmutable $postedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGitHubPayload(array $payload): self
    {
        return new self(
            id: self::requiredId($payload),
            htmlUrl: self::requiredString($payload, 'html_url'),
            postedAt: self::requiredTimestamp($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredId(array $payload): string
    {
        $value = $payload['id'] ?? null;

        if (is_int($value) || (is_string($value) && $value !== '')) {
            return (string) $value;
        }

        throw new \UnexpectedValueException('GitHub publication payload is missing id.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException("GitHub publication payload is missing {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredTimestamp(array $payload): CarbonImmutable
    {
        $value = $payload['created_at'] ?? $payload['submitted_at'] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException('GitHub publication payload is missing a timestamp.');
        }

        return CarbonImmutable::parse($value);
    }
}
