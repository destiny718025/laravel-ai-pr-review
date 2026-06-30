<?php

namespace Tests\Unit\AI;

use App\Data\AI\AIReviewRequest;
use App\Data\AI\CodexAuthCredentials;
use App\Services\AI\CodexAuthCacheReader;
use App\Services\AI\HttpOpenAICodexOAuthReviewProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
                && $request->hasHeader('Accept', 'text/event-stream')
                && $request->hasHeader('Content-Type', 'application/json')
                && $payload['store'] === false
                && $payload['stream'] === true
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

    public function test_provider_extracts_review_json_from_streaming_output_text_delta_events(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(implode("\n", [
                'event: response.output_text.delta',
                'data: {"type":"response.output_text.delta","delta":"{\"findings\""}',
                '',
                'event: response.output_text.delta',
                'data: {"type":"response.output_text.delta","delta":":[]}"}',
                '',
                'event: response.completed',
                'data: {"type":"response.completed"}',
                '',
                'data: [DONE]',
                '',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://chatgpt.test/backend-api/codex/responses'
                && $payload['store'] === false
                && $payload['stream'] === true;
        });
        Http::assertSentCount(1);
    }

    public function test_provider_uses_sse_event_name_when_stream_data_omits_type(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(implode("\n", [
                'event: response.output_text.delta',
                'data: {"delta":"{\"findings\""}',
                '',
                'event: response.output_text.delta',
                'data: {"delta":":[]}"}',
                '',
                'event: response.completed',
                'data: {"id":"resp_123","object":"response","status":"completed","output":[]}',
                '',
                'data: [DONE]',
                '',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(1);
    }

    public function test_provider_extracts_review_json_from_streaming_nested_output_item_events(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(implode("\n", [
                'event: response.output_item.done',
                'data: {"type":"response.output_item.done","item":{"type":"message","content":[{"type":"output_text","text":"{\"findings\":[]}"}]}}',
                '',
                'data: [DONE]',
                '',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(1);
    }

    public function test_provider_ignores_pending_stream_events_until_output_arrives(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(implode("\n", [
                'event: response.created',
                'data: {"id":"resp_123","object":"response","status":"in_progress","output":[]}',
                '',
                'event: response.output_text.delta',
                'data: {"type":"response.output_text.delta","delta":"{\"findings\":[]}"}',
                '',
                'event: response.completed',
                'data: {"type":"response.completed"}',
                '',
                'data: [DONE]',
                '',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(1);
    }

    public function test_provider_retrieves_completed_stream_response_when_output_is_missing(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(implode("\n", [
                'event: response.created',
                'data: {"id":"resp_123","object":"response","status":"in_progress","output":[]}',
                '',
                'event: response.completed',
                'data: {"id":"resp_123","object":"response","status":"completed","output":[]}',
                '',
                'data: [DONE]',
                '',
            ]), 200, ['Content-Type' => 'text/event-stream']),
            'https://chatgpt.test/backend-api/codex/responses/resp_123' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'completed',
                'output_text' => '{"findings":[]}',
            ]),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(2);
    }

    public function test_provider_reports_stream_event_types_for_unsupported_streaming_shapes(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response(implode("\n", [
                'event: response.created',
                'data: {"type":"response.created"}',
                '',
                'event: response.completed',
                'data: {"type":"response.completed"}',
                '',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Event types seen: response.created, response.completed.');

        app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
    }

    public function test_provider_extracts_review_json_from_wrapped_response_object(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'type' => 'response.completed',
                'response' => [
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
                ],
            ]),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(1);
    }

    public function test_provider_polls_in_progress_response_until_completed(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'in_progress',
                'output' => [],
            ]),
            'https://chatgpt.test/backend-api/codex/responses/resp_123' => Http::sequence()
                ->push([
                    'id' => 'resp_123',
                    'object' => 'response',
                    'status' => 'in_progress',
                    'output' => [],
                ])
                ->push([
                    'id' => 'resp_123',
                    'object' => 'response',
                    'status' => 'completed',
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
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(3);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://chatgpt.test/backend-api/codex/responses'
                && $request->method() === 'POST'
                && $request->hasHeader('Accept', 'text/event-stream');
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://chatgpt.test/backend-api/codex/responses/resp_123'
                && $request->method() === 'GET'
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_provider_polls_in_progress_response_with_padded_status(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => " in_progress\n",
                'output' => [],
            ]),
            'https://chatgpt.test/backend-api/codex/responses/resp_123' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'completed',
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
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(2);
    }

    public function test_provider_polls_empty_response_with_id_even_when_status_is_unexpected(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'mystery_pending_status',
                'output' => [],
            ]),
            'https://chatgpt.test/backend-api/codex/responses/resp_123' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'completed',
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
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(2);
    }

    public function test_provider_polls_empty_response_with_scalar_id(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'id' => 123,
                'object' => 'response',
                'status' => 'in_progress',
                'output' => [],
            ]),
            'https://chatgpt.test/backend-api/codex/responses/123' => Http::response([
                'id' => 123,
                'object' => 'response',
                'status' => 'completed',
                'output_text' => '{"findings":[]}',
            ]),
        ]);

        $json = app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSentCount(2);
    }

    public function test_provider_fails_safely_when_polling_limit_is_reached(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider(['services.codex.poll_attempts' => 2]);

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'in_progress',
                'output' => [],
            ]),
            'https://chatgpt.test/backend-api/codex/responses/resp_123' => Http::response([
                'id' => 'resp_123',
                'object' => 'response',
                'status' => 'in_progress',
                'output' => [],
            ]),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Codex response did not complete before polling limit.');

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } finally {
            Http::assertSentCount(3);
        }
    }

    public function test_provider_reports_non_streaming_response_shape_without_raw_text(): void
    {
        $this->fakeCredentials('codex-access-token', 'acct-123');
        $this->configureProvider();

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'id' => 'resp_123',
                'type' => 'response.completed',
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'reasoning',
                        'summary' => [
                            [
                                'type' => 'summary_text',
                                'text' => 'do not leak this text',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } catch (\UnexpectedValueException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('Top-level keys: id, type, status, output.', $message);
            $this->assertStringContainsString('Type: response.completed.', $message);
            $this->assertStringContainsString('Status: completed.', $message);
            $this->assertStringContainsString('Output item types: reasoning.', $message);
            $this->assertStringContainsString('Content part types: none.', $message);
            $this->assertStringNotContainsString('do not leak this text', $message);

            return;
        }

        $this->fail('Expected unsupported response shape exception.');
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

        $this->expectException(ConnectionException::class);

        try {
            app(HttpOpenAICodexOAuthReviewProvider::class)->review($this->request());
        } finally {
            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://chatgpt.test/backend-api/codex/responses';
            });
            Http::assertSentCount(1);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureProvider(array $overrides = []): void
    {
        config(array_merge([
            'services.codex.base_url' => 'https://chatgpt.test/backend-api/codex',
            'services.codex.timeout' => 7,
            'services.codex.poll_attempts' => 5,
            'services.codex.poll_sleep_ms' => 0,
            'services.openai.base_url' => 'https://api.openai.test',
            'services.openai.api_key' => 'sk-openai-should-not-be-used',
        ], $overrides));
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
