<?php

namespace Tests\Unit\GitHub;

use App\Services\GitHub\GitHubFailureMapper;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class GitHubFailureMapperTest extends TestCase
{
    public function test_maps_not_found_or_unreadable_response_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->map($this->requestException(404));

        $this->assertSame('not_found_or_unreadable', $failure->code);
        $this->assertSame('GitHub could not find or read this pull request.', $failure->message);
    }

    public function test_maps_rate_limit_response_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->map($this->requestException(403, [
            'X-RateLimit-Remaining' => '0',
        ]));

        $this->assertSame('rate_limited', $failure->code);
        $this->assertSame('GitHub rate limit was reached. Try fetching this pull request again later.', $failure->message);
    }

    public function test_maps_authenticated_rejection_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->map($this->requestException(401));

        $this->assertSame('auth_failed', $failure->code);
        $this->assertSame('GitHub rejected the configured token. Check the token before trying again.', $failure->message);
    }

    public function test_maps_transport_failure_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->map(new ConnectionException('curl failed with token secret'));

        $this->assertSame('server_unavailable', $failure->code);
        $this->assertSame('GitHub could not be reached. Try fetching this pull request again later.', $failure->message);
    }

    public function test_maps_malformed_payload_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->map(new \UnexpectedValueException('raw payload missing head.sha'));

        $this->assertSame('malformed_response', $failure->code);
        $this->assertSame('GitHub returned an unexpected response. Try again later.', $failure->message);
    }

    public function test_maps_publication_target_validation_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->mapPublication($this->requestException(422));

        $this->assertSame('target_invalid', $failure->code);
        $this->assertSame('GitHub could not apply this comment to the requested pull request location.', $failure->message);
        $this->assertStringNotContainsString('raw upstream body', $failure->message);
    }

    public function test_maps_publication_authenticated_rejection_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->mapPublication($this->requestException(401, [
            'Authorization' => 'Bearer secret-token',
        ]));

        $this->assertSame('auth_failed', $failure->code);
        $this->assertSame('GitHub rejected the configured token. Check the token before publishing again.', $failure->message);
        $this->assertStringNotContainsString('secret-token', $failure->message);
    }

    public function test_maps_publication_rate_limit_response_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->mapPublication($this->requestException(403, [
            'X-RateLimit-Remaining' => '0',
        ]));

        $this->assertSame('rate_limited', $failure->code);
        $this->assertSame('GitHub rate limit was reached. Try publishing comments again later.', $failure->message);
    }

    public function test_maps_publication_malformed_payload_to_safe_message(): void
    {
        $failure = app(GitHubFailureMapper::class)->mapPublication(new \UnexpectedValueException('html_url missing from raw payload'));

        $this->assertSame('malformed_response', $failure->code);
        $this->assertSame('GitHub returned an unexpected publication response. Try again later.', $failure->message);
        $this->assertStringNotContainsString('html_url', $failure->message);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function requestException(int $status, array $headers = []): RequestException
    {
        $response = new Response(new PsrResponse(
            $status,
            $headers,
            json_encode(['message' => 'raw upstream body'], JSON_THROW_ON_ERROR),
        ));

        return new RequestException($response);
    }
}
