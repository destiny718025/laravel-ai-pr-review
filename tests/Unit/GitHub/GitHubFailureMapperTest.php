<?php

namespace Tests\Unit\GitHub;

use App\Services\GitHub\GitHubFailureMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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

    /**
     * @param  array<string, string>  $headers
     */
    private function requestException(int $status, array $headers = []): RequestException
    {
        $response = new Response(Http::response(['message' => 'raw upstream body'], $status, $headers));

        return new RequestException($response);
    }
}
