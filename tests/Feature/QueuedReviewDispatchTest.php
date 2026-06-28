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
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedReviewDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_page_shows_run_ai_review_action_only_after_github_snapshot_exists(): void
    {
        $reviewRun = $this->createReviewRun();

        $this->get(route('reviews.show', $reviewRun))
            ->assertOk()
            ->assertDontSee('Run AI Review')
            ->assertSee('Fetch GitHub pull request data before running AI review.');

        $this->storeGitHubSnapshot($reviewRun);

        $this->get(route('reviews.show', $reviewRun))
            ->assertOk()
            ->assertSee('Run AI Review')
            ->assertDontSee('Fetch GitHub pull request data before running AI review.');
    }

    public function test_run_requires_github_snapshot_before_queueing_review(): void
    {
        Queue::fake();

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.run', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'github_snapshot_missing')
            ->assertSessionHas('service_error_message', 'Fetch GitHub pull request data before running AI review.');

        $reviewRun = ReviewRun::findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Pending, $reviewRun->status);
        $this->assertNull($reviewRun->queued_at);
        Queue::assertNothingPushed();
    }

    public function test_run_queues_job_and_does_not_execute_provider_inline(): void
    {
        Queue::fake();

        $this->app->instance(AIReviewProvider::class, new class implements AIReviewProvider
        {
            public function review(AIReviewRequest $request): string
            {
                throw new \RuntimeException('Provider should not run during HTTP dispatch.');
            }
        });

        $reviewRun = $this->createReviewRun();
        $this->storeGitHubSnapshot($reviewRun);

        $this->post(route('reviews.run', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'AI review queued.');

        $reviewRun = ReviewRun::findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Queued, $reviewRun->status);
        $this->assertNotNull($reviewRun->queued_at);
        $this->assertNull($reviewRun->started_at);
        $this->assertNull($reviewRun->completed_at);
        Queue::assertPushed(ExecuteReviewRunJob::class, fn (ExecuteReviewRunJob $job): bool => $job->reviewRunId === $reviewRun->id);
    }

    private function createReviewRun(): ReviewRun
    {
        return app(ReviewRunService::class)
            ->createFromPullRequestUrl('https://github.com/laravel/framework/pull/1')
            ->reviewRun();
    }

    private function storeGitHubSnapshot(ReviewRun $reviewRun): ReviewRun
    {
        return app(ReviewRunRepository::class)->storeGitHubSnapshot(
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
    }
}
