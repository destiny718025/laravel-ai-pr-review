<?php

namespace App\Services\AI;

use App\Data\AI\AIReviewFailure;
use App\Exceptions\AI\CodexAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use JsonException;

class AIReviewFailureMapper
{
    public function map(\Throwable $throwable): AIReviewFailure
    {
        if ($throwable instanceof CodexAuthException) {
            return $this->mapCodexAuthException($throwable);
        }

        if ($throwable instanceof RequestException) {
            return $this->mapRequestException($throwable);
        }

        if ($throwable instanceof JsonException) {
            return new AIReviewFailure(
                'invalid_json',
                'AI provider returned invalid JSON. Try running the review again.',
            );
        }

        if ($throwable instanceof ValidationException) {
            return new AIReviewFailure(
                'invalid_schema',
                'AI provider returned an unexpected review format. Try running the review again.',
            );
        }

        if ($throwable instanceof \UnexpectedValueException) {
            return $this->mapUnexpectedValueException($throwable);
        }

        if ($throwable instanceof ConnectionException) {
            return new AIReviewFailure(
                'provider_unavailable',
                'AI provider could not be reached. Try running the review again later.',
            );
        }

        return new AIReviewFailure(
            'unexpected_failure',
            'AI review failed unexpectedly. Try running the review again.',
        );
    }

    private function mapCodexAuthException(CodexAuthException $exception): AIReviewFailure
    {
        return match ($exception->reason()) {
            'auth_cache_missing', 'auth_cache_unreadable' => new AIReviewFailure(
                'auth_unavailable',
                'Codex authentication is unavailable. Sign in with Codex CLI and try again.',
            ),
            'auth_cache_malformed', 'access_token_missing' => new AIReviewFailure(
                'auth_invalid',
                'Codex authentication is invalid. Re-authenticate with Codex CLI and try again.',
            ),
            default => new AIReviewFailure(
                'auth_invalid',
                'Codex authentication is invalid. Re-authenticate with Codex CLI and try again.',
            ),
        };
    }

    private function mapRequestException(RequestException $exception): AIReviewFailure
    {
        $status = $exception->response?->status();

        return match ($status) {
            401, 403 => new AIReviewFailure(
                'auth_rejected',
                'AI provider rejected the current authentication. Refresh the active provider credentials and try again.',
            ),
            429 => new AIReviewFailure(
                'rate_limited',
                'AI provider rate limit was reached. Try running the review again later.',
            ),
            default => new AIReviewFailure(
                'provider_request_failed',
                'AI provider request failed. Try running the review again later.',
            ),
        };
    }

    private function mapUnexpectedValueException(\UnexpectedValueException $exception): AIReviewFailure
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'unsupported response shape')) {
            return new AIReviewFailure(
                'invalid_response_shape',
                'AI provider returned an unsupported response shape. Try running the review again.',
            );
        }

        if (
            str_contains($message, 'response must be an object')
            || str_contains($message, 'response output must be an array')
            || str_contains($message, 'response output_text must be a string')
        ) {
            return new AIReviewFailure(
                'malformed_response',
                'AI provider returned an unexpected response. Try running the review again.',
            );
        }

        return new AIReviewFailure(
            'invalid_schema',
            'AI provider returned an unexpected review format. Try running the review again.',
        );
    }
}
