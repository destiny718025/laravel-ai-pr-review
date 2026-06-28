<?php

namespace App\Services\AI;

use App\Data\AI\AIReviewFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use JsonException;

class AIReviewFailureMapper
{
    public function map(\Throwable $throwable): AIReviewFailure
    {
        if ($throwable instanceof JsonException) {
            return new AIReviewFailure(
                'invalid_json',
                'AI provider returned invalid JSON. Try running the review again.',
            );
        }

        if ($throwable instanceof ValidationException || $throwable instanceof \UnexpectedValueException) {
            return new AIReviewFailure(
                'invalid_schema',
                'AI provider returned an unexpected review format. Try running the review again.',
            );
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
}
