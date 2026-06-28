<?php

namespace App\Services\GitHub;

use App\Data\GitHub\GitHubFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class GitHubFailureMapper
{
    public function map(\Throwable $throwable): GitHubFailure
    {
        if ($throwable instanceof RequestException) {
            return $this->mapRequestException($throwable);
        }

        if ($throwable instanceof ConnectionException) {
            return new GitHubFailure(
                'server_unavailable',
                'GitHub could not be reached. Try fetching this pull request again later.',
            );
        }

        if ($throwable instanceof \UnexpectedValueException) {
            return new GitHubFailure(
                'malformed_response',
                'GitHub returned an unexpected response. Try again later.',
            );
        }

        return new GitHubFailure(
            'server_unavailable',
            'GitHub could not be reached. Try fetching this pull request again later.',
        );
    }

    private function mapRequestException(RequestException $exception): GitHubFailure
    {
        $response = $exception->response;
        $status = $response->status();

        if ($status === 401 || $status === 403 && $this->hasConfiguredToken() && ! $this->isRateLimited($exception)) {
            return new GitHubFailure(
                'auth_failed',
                'GitHub rejected the configured token. Check the token before trying again.',
            );
        }

        if ($status === 403 && $this->isRateLimited($exception)) {
            return new GitHubFailure(
                'rate_limited',
                'GitHub rate limit was reached. Try fetching this pull request again later.',
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
            'GitHub could not be reached. Try fetching this pull request again later.',
        );
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
