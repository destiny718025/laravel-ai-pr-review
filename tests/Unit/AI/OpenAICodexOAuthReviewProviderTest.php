<?php

namespace Tests\Unit\AI;

use App\Data\AI\AIReviewRequest;
use App\Data\AI\CodexAuthCredentials;
use App\Services\AI\CodexAuthCacheReader;
use App\Services\AI\HttpOpenAICodexOAuthReviewProvider;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAICodexOAuthReviewProviderTest extends TestCase
{
    public function test_provider_posts_review_context_to_codex_responses_endpoint_and_returns_output_text_parts(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => '{"findings":[]}',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $reviewInput = json_decode((string) $payload['input'][1]['content'], true, 512, JSON_THROW_ON_ERROR);

            return $request->url() === 'https://chatgpt.test/backend-api/codex/responses'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer codex-access-token')
                && $request->hasHeader('ChatGPT-Account-ID', 'acct-123')
                && $payload['input'][0]['role'] === 'system'
                && $payload['input'][0]['content'] === 'Review this PR.'
                && $reviewInput['repository'] === 'laravel/framework'
                && $reviewInput['pull_request_number'] === 1
                && $reviewInput['source_url'] === 'https://github.com/laravel/framework/pull/1'
                && $reviewInput['head_sha'] === 'abc123def4567890abc123def4567890abc12345'
                && $reviewInput['title'] === 'Add queued AI review'
                && $reviewInput['files'][0]['filename'] === 'app/Example.php';
        });
        Http::assertSentCount(1);
    }

    public function test_provider_accepts_text_parts_and_omits_account_header_when_not_available(): void
    {
        $this->fakeCredentials('codex-access-token');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => '{"findings":[{"title":"Example"}]}',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[{"title":"Example"}]}', $json);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://chatgpt.test/backend-api/codex/responses'
                && ! $request->hasHeader('ChatGPT-Account-ID');
        });
        Http::assertSentCount(1);
    }

    public function test_provider_falls_back_to_top_level_output_text_when_message_parts_are_missing(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output_text' => '{"findings":[{"title":"Fallback"}]}',
            ]),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[{"title":"Fallback"}]}', $json);
        Http::assertSentCount(1);
    }

    public function test_provider_rejects_unsupported_success_shape_without_falling_back_to_api_key_transport(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'image',
                                'text' => '{"findings":[]}',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.openai.test/*' => Http::response(['output_text' => '{"findings":[{"title":"fallback"}]}']),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('unsupported response shape');

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } finally {
            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://chatgpt.test/backend-api/codex/responses';
            });
            Http::assertSentCount(1);
        }
    }

    public function test_provider_throws_unauthorized_backend_errors_without_retrying_openai_api_key_transport(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(
                ['error' => 'unauthorized'],
                401,
            ),
            'https://api.openai.test/*' => Http::response(['output_text' => '{"findings":[{"title":"fallback"}]}']),
        ]);

        $this->expectException(RequestException::class);

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } finally {
            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://chatgpt.test/backend-api/codex/responses';
            });
            Http::assertSentCount(1);
        }
    }

    public function test_provider_throws_rate_limit_backend_errors_without_retrying_openai_api_key_transport(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(
                ['error' => 'rate_limited'],
                429,
            ),
            'https://api.openai.test/*' => Http::response(['output_text' => '{"findings":[{"title":"fallback"}]}']),
        ]);

        $this->expectException(RequestException::class);

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } finally {
            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://chatgpt.test/backend-api/codex/responses';
            });
            Http::assertSentCount(1);
        }
    }

    public function test_provider_throws_transport_errors_without_retrying_openai_api_key_transport(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::failedConnection(),
            'https://api.openai.test/*' => Http::response(['output_text' => '{"findings":[{"title":"fallback"}]}']),
        ]);

        $this->expectException(\Illuminate\Http\Client\ConnectionException::class);

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } finally {
            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://chatgpt.test/backend-api/codex/responses';
            });
            Http::assertSentCount(1);
        }
    }

    private function configureProvider(): void
    {
        config([
            'services.codex.base_url' => 'https://chatgpt.test/backend-api/codex',
            'services.codex.timeout' => 7,
            'services.openai.base_url' => 'https://api.openai.test',
            'services.openai.api_key' => 'sk-openai-should-not-be-used',
        ]);
    }

    private function fakeCredentials(string $accessToken, ?string $accountId = null): void
    {
        $this->app->instance(CodexAuthCacheReader::class, new class($accessToken, $accountId) extends CodexAuthCacheReader
        {
            public function __construct(
                private readonly string $accessToken,
                private readonly ?string $accountId,
            ) {}

            public function read(): CodexAuthCredentials
            {
                return new CodexAuthCredentials(
                    accessToken: $this->accessToken,
                    accountId: $this->accountId,
                    authMode: 'chatgpt',
                    lastRefresh: '2026-06-30T00:00:00Z',
                );
            }
        });
    }

    private function request(): AIReviewRequest
    {
        return new AIReviewRequest(
            repositoryFullName: 'laravel/framework',
            pullRequestNumber: 1,
            sourceUrl: 'https://github.com/laravel/framework/pull/1',
            headSha: 'abc123def4567890abc123def4567890abc12345',
            title: 'Add queued AI review',
            changedFiles: [
                [
                    'filename' => 'app/Example.php',
                    'patch' => '@@ -1 +1 @@',
                    'sha' => '1111111111111111111111111111111111111111',
                ],
            ],
            instructions: 'Review this PR.',
        );
    }
}
