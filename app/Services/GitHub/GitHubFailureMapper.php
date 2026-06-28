<?php

namespace App\Services\GitHub;

use App\Data\GitHub\GitHubFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class GitHubFailureMapper
{
    public function map(\Throwable $throwable): GitHubFailure
    {
        return $this->mapForContext($throwable, 'fetch');
    }

    public function mapPublication(\Throwable $throwable): GitHubFailure
    {
        return $this->mapForContext($throwable, 'publication');
    }

    private function mapForContext(\Throwable $throwable, string $context): GitHubFailure
    {
        if ($throwable instanceof RequestException) {
            return $this->mapRequestException($throwable, $context);
        }

        if ($throwable instanceof ConnectionException) {
            return new GitHubFailure(
                'server_unavailable',
                $this->serverUnavailableMessage($context),
            );
        }

        if ($throwable instanceof \UnexpectedValueException) {
            return new GitHubFailure(
                'malformed_response',
                $this->malformedResponseMessage($context),
            );
        }

        return new GitHubFailure(
            'server_unavailable',
            $this->serverUnavailableMessage($context),
        );
    }

    private function mapRequestException(RequestException $exception, string $context): GitHubFailure
    {
        $response = $exception->response;
        $status = $response->status();

        if ($context === 'publication' && ($status === 400 || $status === 422)) {
            return new GitHubFailure(
                'target_invalid',
                'GitHub could not apply this comment to the requested pull request location.',
            );
        }

        if ($status === 401 || $status === 403 && $this->hasConfiguredToken() && ! $this->isRateLimited($exception)) {
            return new GitHubFailure(
                'auth_failed',
                $this->authFailedMessage($context),
            );
        }

        if ($status === 403 && $this->isRateLimited($exception)) {
            return new GitHubFailure(
                'rate_limited',
                $this->rateLimitedMessage($context),
            );
        }

        if ($status === 404) {
            return new GitHubFailure(
                'not_found_or_unreadable',
                'GitHub could not find or read this pull request.',
            );
        }

        return new GitHubFailure(
            'server_unavailable',
            $this->serverUnavailableMessage($context),
        );
    }

    private function authFailedMessage(string $context): string
    {
        if ($context === 'publication') {
            return 'GitHub rejected the configured token. Check the token before publishing again.';
        }

        return 'GitHub rejected the configured token. Check the token before trying again.';
    }

    private function malformedResponseMessage(string $context): string
    {
        if ($context === 'publication') {
            return 'GitHub returned an unexpected publication response. Try again later.';
        }

        return 'GitHub returned an unexpected response. Try again later.';
    }

    private function rateLimitedMessage(string $context): string
    {
        if ($context === 'publication') {
            return 'GitHub rate limit was reached. Try publishing comments again later.';
        }

        return 'GitHub rate limit was reached. Try fetching this pull request again later.';
    }

    private function serverUnavailableMessage(string $context): string
    {
        if ($context === 'publication') {
            return 'GitHub could not be reached. Try publishing comments again later.';
        }

        return 'GitHub could not be reached. Try fetching this pull request again later.';
    }

    private function isRateLimited(RequestException $exception): bool
    {
        return $exception->response->header('X-RateLimit-Remaining') === '0';
    }

    private function hasConfiguredToken(): bool
    {
        $token = config('services.github.token');

        return is_string($token) && $token !== '';
    }
}
