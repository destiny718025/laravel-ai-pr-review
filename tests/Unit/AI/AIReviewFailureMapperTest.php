<?php

namespace Tests\Unit\AI;

use App\Exceptions\AI\CodexAuthException;
use App\Services\AI\AIReviewFailureMapper;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use JsonException;
use Tests\TestCase;

class AIReviewFailureMapperTest extends TestCase
{
    public function test_missing_codex_auth_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(new CodexAuthException(
            'auth_cache_missing',
            'raw auth cache with access token secret',
        ));

        $this->assertSame('auth_unavailable', $failure->code);
        $this->assertSame('Codex authentication is unavailable. Sign in with Codex CLI and try again.', $failure->message);
        $this->assertStringNotContainsString('secret', $failure->message);
    }

    public function test_malformed_codex_auth_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(new CodexAuthException(
            'auth_cache_malformed',
            'raw auth cache body',
        ));

        $this->assertSame('auth_invalid', $failure->code);
        $this->assertSame('Codex authentication is invalid. Re-authenticate with Codex CLI and try again.', $failure->message);
        $this->assertStringNotContainsString('raw auth cache body', $failure->message);
    }

    public function test_unauthorized_request_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map($this->requestException(401));

        $this->assertSame('auth_rejected', $failure->code);
        $this->assertSame('AI provider rejected the current authentication. Refresh the active provider credentials and try again.', $failure->message);
    }

    public function test_forbidden_request_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map($this->requestException(403));

        $this->assertSame('auth_rejected', $failure->code);
        $this->assertSame('AI provider rejected the current authentication. Refresh the active provider credentials and try again.', $failure->message);
    }

    public function test_rate_limited_request_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map($this->requestException(429));

        $this->assertSame('rate_limited', $failure->code);
        $this->assertSame('AI provider rate limit was reached. Try running the review again later.', $failure->message);
    }

    public function test_json_decode_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(new JsonException('raw json payload'));

        $this->assertSame('invalid_json', $failure->code);
        $this->assertSame('AI provider returned invalid JSON. Try running the review again.', $failure->message);
        $this->assertStringNotContainsString('raw json payload', $failure->message);
    }

    public function test_schema_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(
            ValidationException::withMessages(['findings' => 'raw provider payload']),
        );

        $this->assertSame('invalid_schema', $failure->code);
        $this->assertSame('AI provider returned an unexpected review format. Try running the review again.', $failure->message);
    }

    public function test_transport_timeout_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(
            new ConnectionException('Authorization: Bearer sk-secret'),
        );

        $this->assertSame('provider_unavailable', $failure->code);
        $this->assertSame('AI provider could not be reached. Try running the review again later.', $failure->message);
        $this->assertStringNotContainsString('sk-secret', $failure->message);
    }

    public function test_malformed_provider_response_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(
            new \UnexpectedValueException('Codex response must be an object.'),
        );

        $this->assertSame('malformed_response', $failure->code);
        $this->assertSame('AI provider returned an unexpected response. Try running the review again.', $failure->message);
    }

    public function test_unsupported_response_shape_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(
            new \UnexpectedValueException('Codex returned an unsupported response shape.'),
        );

        $this->assertSame('invalid_response_shape', $failure->code);
        $this->assertSame('AI provider returned an unsupported response shape. Try running the review again.', $failure->message);
    }

    public function test_unexpected_runtime_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(
            new \RuntimeException('raw provider payload with secret'),
        );

        $this->assertSame('unexpected_failure', $failure->code);
        $this->assertSame('AI review failed unexpectedly. Try running the review again.', $failure->message);
    }

    private function requestException(int $status): RequestException
    {
        $response = new Response(new PsrResponse(
            $status,
            ['Authorization' => 'Bearer codex-secret'],
            json_encode(['message' => 'raw upstream body'], JSON_THROW_ON_ERROR),
        ));

        return new RequestException($response);
    }
}
