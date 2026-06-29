<?php

namespace Tests\Feature;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Data\AI\CodexAuthCredentials;
use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;
use App\Enums\ReviewRunStatus;
use App\Exceptions\AI\CodexAuthException;
use App\Jobs\ExecuteReviewRunJob;
use App\Models\ReviewRun;
use App\Repositories\ReviewRunRepository;
use App\Services\AI\CodexAuthCacheReader;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\ReviewExecutionService;
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QueuedReviewFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_provider_json_marks_run_failed_with_safe_summary_only(): void
    {
        $this->app->instance(AIReviewProvider::class, new class implements AIReviewProvider
        {
            public function review(AIReviewRequest $request): string
            {
                return '{"findings": [';
            }
        });

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider returned invalid JSON. Try running the review again.');
        $this->assertDatabaseCount('review_findings', 0);
    }

    public function test_invalid_provider_schema_marks_run_failed_without_raw_payload_fragments(): void
    {
        $this->app->instance(
            AIReviewProvider::class,
            new FakeAIReviewProvider(base_path('tests/Fixtures/AI/fake-review-invalid.json')),
        );

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider returned an unexpected review format. Try running the review again.');
        $this->assertDatabaseCount('review_findings', 0);
    }

    public function test_provider_transport_failure_marks_run_failed_with_safe_summary(): void
    {
        $this->app->instance(AIReviewProvider::class, new class implements AIReviewProvider
        {
            public function review(AIReviewRequest $request): string
            {
                throw new ConnectionException('Timeout with Authorization: Bearer sk-secret');
            }
        });

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider could not be reached. Try running the review again later.');
        $this->assertDatabaseCount('review_findings', 0);
    }

    public function test_codex_missing_auth_marks_run_failed_with_safe_summary_only(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexFailure('auth_cache_missing', 'Bearer codex-access refresh_token id_token raw auth cache body');

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'Codex authentication is unavailable. Sign in with Codex CLI and try again.');
    }

    public function test_codex_malformed_auth_marks_run_failed_without_raw_auth_cache_fragments(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexFailure('auth_cache_malformed', '{"access_token":"codex-access","refresh_token":"refresh-secret","id_token":"id-secret"}');

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'Codex authentication is invalid. Re-authenticate with Codex CLI and try again.');
    }

    public function test_codex_unauthorized_failure_persists_safe_summary_without_account_header_fragments(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexCredentials('codex-access-token', 'acct-123');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'error' => 'Authorization: Bearer codex-access-token rejected for ChatGPT-Account-ID acct-123',
            ], 401),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider rejected the current authentication. Refresh the active provider credentials and try again.');
        Http::assertSentCount(1);
    }

    public function test_codex_forbidden_failure_persists_safe_summary_without_backend_body_fragments(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexCredentials('codex-access-token', 'acct-123');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'error' => 'ChatGPT-Account-ID acct-123 rejected',
                'raw_backend' => '{"Authorization":"Bearer codex-access-token"}',
            ], 403),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider rejected the current authentication. Refresh the active provider credentials and try again.');
        Http::assertSentCount(1);
    }

    public function test_codex_rate_limit_failure_persists_safe_summary_without_backend_fragments(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexCredentials('codex-access-token', 'acct-123');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'error' => 'rate limited for Bearer codex-access-token',
                'raw_backend' => '{"message":"too many requests"}',
            ], 429),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider rate limit was reached. Try running the review again later.');
        Http::assertSentCount(1);
    }

    public function test_codex_transport_failure_persists_safe_summary_without_header_fragments(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexCredentials('codex-access-token', 'acct-123');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::failedConnection(),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider could not be reached. Try running the review again later.');
        Http::assertSentCount(1);
    }

    public function test_codex_malformed_response_persists_safe_summary_without_raw_backend_body(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexCredentials('codex-access-token', 'acct-123');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output_text' => [
                    'raw_backend' => '{"access_token":"codex-access-token"}',
                ],
            ]),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider returned an unexpected response. Try running the review again.');
        Http::assertSentCount(1);
    }

    public function test_codex_unsupported_response_shape_persists_safe_summary_without_backend_fragments(): void
    {
        $this->configureCodexProvider();
        $this->fakeCodexCredentials('codex-access-token', 'acct-123');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'image',
                                'text' => '{"access_token":"codex-access-token"}',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.openai.test/*' => Http::response(['should_not' => 'be_used']),
        ]);

        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI provider returned an unsupported response shape. Try running the review again.');
        Http::assertSentCount(1);
    }

    public function test_successful_retry_replaces_stale_findings_and_clears_failure_state(): void
    {
        $reviewRun = $this->queuedReviewRun();

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertDatabaseCount('review_findings', 2);

        $this->app->instance(AIReviewProvider::class, new class implements AIReviewProvider
        {
            public function review(AIReviewRequest $request): string
            {
                throw new \RuntimeException('Raw provider payload with sk-secret');
            }
        });

        app(ReviewRunRepository::class)->queueForExecution(ReviewRun::findOrFail($reviewRun->id));
        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertFailedSafely($reviewRun, 'AI review failed unexpectedly. Try running the review again.');
        $this->assertDatabaseCount('review_findings', 2);

        $this->app->instance(AIReviewProvider::class, new FakeAIReviewProvider);

        app(ReviewRunRepository::class)->queueForExecution(ReviewRun::findOrFail($reviewRun->id));
        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $reviewRun = ReviewRun::query()->with(['findings', 'currentFindings'])->findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Completed, $reviewRun->status);
        $this->assertNull($reviewRun->safe_error_message);
        $this->assertNull($reviewRun->failed_at);
        $this->assertCount(4, $reviewRun->findings);
        $this->assertCount(2, $reviewRun->currentFindings);
        $this->assertDatabaseHas('review_findings', [
            'review_run_id' => $reviewRun->id,
            'title' => 'Unhandled malformed upstream payload',
        ]);
    }

    private function queuedReviewRun(): ReviewRun
    {
        $reviewRun = app(ReviewRunService::class)
            ->createFromPullRequestUrl('https://github.com/laravel/framework/pull/1')
            ->reviewRun();

        $reviewRun = app(ReviewRunRepository::class)->storeGitHubSnapshot(
            $reviewRun,
            new PullRequestSnapshot(
                title: 'Add queued AI review',
                state: 'open',
                headSha: 'abc123def4567890abc123def4567890abc12345',
            ),
            [
                new PullRequestFileSnapshot(
                    filename: 'app/Services/GitHub/HttpGitHubClient.php',
                    patch: '@@ -1 +1 @@',
                    sha: '1111111111111111111111111111111111111111',
                ),
            ],
        );

        return app(ReviewRunRepository::class)->queueForExecution($reviewRun);
    }

    private function assertFailedSafely(ReviewRun $reviewRun, string $expectedMessage): void
    {
        $reviewRun = ReviewRun::findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Failed, $reviewRun->status);
        $this->assertSame($expectedMessage, $reviewRun->safe_error_message);
        $this->assertNotNull($reviewRun->failed_at);
        $this->assertNull($reviewRun->completed_at);

        foreach ([
            'Authorization',
            'Bearer',
            'sk-secret',
            'codex-access-token',
            'refresh_token',
            'id_token',
            'raw auth cache body',
            'raw backend body',
            'raw provider payload',
            'secret fragment',
            'ChatGPT-Account-ID',
            'acct-123',
        ] as $fragment) {
            $this->assertStringNotContainsString($fragment, (string) $reviewRun->safe_error_message);
        }
    }

    private function configureCodexProvider(): void
    {
        config([
            'services.ai.provider' => 'openai_codex_oauth',
            'services.codex.base_url' => 'https://chatgpt.test/backend-api/codex',
            'services.codex.timeout' => 7,
            'services.openai.base_url' => 'https://api.openai.test',
            'services.openai.api_key' => 'sk-openai-should-not-be-used',
            'services.openai.model' => 'gpt-test',
        ]);
    }

    private function fakeCodexCredentials(string $accessToken, ?string $accountId = null): void
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

    private function fakeCodexFailure(string $reason, string $message): void
    {
        $this->app->instance(CodexAuthCacheReader::class, new class($reason, $message) extends CodexAuthCacheReader
        {
            public function __construct(
                private readonly string $reason,
                private readonly string $message,
            ) {}

            public function read(): CodexAuthCredentials
            {
                throw new CodexAuthException($this->reason, $this->message);
            }
        });
    }
}
