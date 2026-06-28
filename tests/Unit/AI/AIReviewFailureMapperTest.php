<?php

namespace Tests\Unit\AI;

use App\Services\AI\AIReviewFailureMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use JsonException;
use Tests\TestCase;

class AIReviewFailureMapperTest extends TestCase
{
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

    public function test_unexpected_runtime_failure_maps_to_safe_summary(): void
    {
        $failure = app(AIReviewFailureMapper::class)->map(
            new \RuntimeException('raw provider payload with secret'),
        );

        $this->assertSame('unexpected_failure', $failure->code);
        $this->assertSame('AI review failed unexpectedly. Try running the review again.', $failure->message);
    }
}
