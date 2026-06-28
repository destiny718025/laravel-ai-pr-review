<?php

namespace Tests\Feature;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;
use App\Enums\ReviewRunStatus;
use App\Jobs\ExecuteReviewRunJob;
use App\Models\ReviewRun;
use App\Repositories\ReviewRunRepository;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\ReviewExecutionService;
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

        $reviewRun = ReviewRun::query()->with('findings')->findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Completed, $reviewRun->status);
        $this->assertNull($reviewRun->safe_error_message);
        $this->assertNull($reviewRun->failed_at);
        $this->assertCount(2, $reviewRun->findings);
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

        foreach (['Authorization', 'Bearer', 'sk-secret', 'raw provider payload', 'secret fragment'] as $fragment) {
            $this->assertStringNotContainsString($fragment, (string) $reviewRun->safe_error_message);
        }
    }
}
