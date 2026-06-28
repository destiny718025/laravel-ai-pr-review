<?php

namespace Tests\Feature;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;
use App\Enums\ReviewRunStatus;
use App\Jobs\ExecuteReviewRunJob;
use App\Models\ReviewCommentDraft;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use App\Repositories\ReviewRunRepository;
use App\Services\ReviewExecutionService;
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedReviewExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_job_marks_run_completed_and_persists_validated_findings(): void
    {
        Queue::fake();

        $reviewRun = $this->createReviewRunWithSnapshot();

        $this->post(route('reviews.run', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'AI review queued.');

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $reviewRun = ReviewRun::query()->with('findings')->findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Completed, $reviewRun->status);
        $this->assertNotNull($reviewRun->queued_at);
        $this->assertNotNull($reviewRun->started_at);
        $this->assertNotNull($reviewRun->completed_at);
        $this->assertNull($reviewRun->safe_error_message);
        $this->assertNull($reviewRun->failed_at);
        $this->assertCount(2, $reviewRun->findings);

        $this->assertDatabaseHas('review_findings', [
            'review_run_id' => $reviewRun->id,
            'severity' => 'high',
            'category' => 'bug',
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'title' => 'Unhandled malformed upstream payload',
        ]);
        $this->assertDatabaseHas('review_findings', [
            'review_run_id' => $reviewRun->id,
            'severity' => 'medium',
            'category' => 'security',
            'suggested_comment_text' => 'Please map provider exceptions to safe summaries before storing or rendering them.',
        ]);
    }

    public function test_review_detail_renders_structured_findings_and_local_draft_generation_without_publish_controls(): void
    {
        $reviewRun = $this->createReviewRunWithSnapshot();
        app(ReviewRunRepository::class)->queueForExecution($reviewRun);

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->get(route('reviews.show', $reviewRun))
            ->assertOk()
            ->assertSee('Structured Findings')
            ->assertSee('High Bug')
            ->assertSee('Unhandled malformed upstream payload')
            ->assertSee('Suggested comment: Please validate the provider response shape before consuming nested fields so malformed responses fail safely.')
            ->assertSee('Comment Drafts')
            ->assertSee('Generate Drafts')
            ->assertDontSee('Approve')
            ->assertDontSee('Publish');
    }

    public function test_successful_retry_supersedes_previous_findings_instead_of_physically_deleting_them(): void
    {
        $reviewRun = $this->createReviewRunWithSnapshot();
        app(ReviewRunRepository::class)->queueForExecution($reviewRun);

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $firstFindingIds = ReviewFinding::query()
            ->where('review_run_id', $reviewRun->id)
            ->pluck('id');

        app(ReviewRunRepository::class)->queueForExecution(ReviewRun::findOrFail($reviewRun->id));
        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $reviewRun = ReviewRun::query()
            ->with(['findings', 'currentFindings'])
            ->findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Completed, $reviewRun->status);
        $this->assertCount(4, $reviewRun->findings);
        $this->assertCount(2, $reviewRun->currentFindings);
        $this->assertSame(
            2,
            ReviewFinding::query()
                ->whereIn('id', $firstFindingIds)
                ->whereNotNull('superseded_at')
                ->count(),
        );
        $this->assertSame(
            2,
            ReviewFinding::query()
                ->where('review_run_id', $reviewRun->id)
                ->whereNull('superseded_at')
                ->count(),
        );
    }

    public function test_successful_retry_preserves_and_marks_existing_drafts_stale_before_new_drafts_are_generated(): void
    {
        $reviewRun = $this->createReviewRunWithSnapshot();
        app(ReviewRunRepository::class)->queueForExecution($reviewRun);

        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->post(route('reviews.drafts.generate', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Generated 2 comment drafts.');

        $firstFindingIds = ReviewFinding::query()
            ->where('review_run_id', $reviewRun->id)
            ->whereNull('superseded_at')
            ->pluck('id');

        $this->assertSame(
            2,
            ReviewCommentDraft::query()
                ->where('review_run_id', $reviewRun->id)
                ->whereNull('stale_at')
                ->count(),
        );

        app(ReviewRunRepository::class)->queueForExecution(ReviewRun::findOrFail($reviewRun->id));
        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertSame(
            2,
            ReviewCommentDraft::query()
                ->where('review_run_id', $reviewRun->id)
                ->whereIn('source_review_finding_id', $firstFindingIds)
                ->whereNotNull('stale_at')
                ->count(),
        );

        $this->post(route('reviews.drafts.generate', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Generated 2 comment drafts.');

        $this->assertSame(4, ReviewCommentDraft::query()->where('review_run_id', $reviewRun->id)->count());
        $this->assertSame(
            2,
            ReviewCommentDraft::query()
                ->where('review_run_id', $reviewRun->id)
                ->whereNull('stale_at')
                ->count(),
        );
    }

    public function test_future_execution_and_retry_requests_include_latest_saved_custom_instructions_without_rewriting_existing_artifacts(): void
    {
        $provider = new class implements AIReviewProvider
        {
            /**
             * @var array<int, AIReviewRequest>
             */
            public array $requests = [];

            public function review(AIReviewRequest $request): string
            {
                $this->requests[] = $request;

                return (string) file_get_contents(base_path('tests/Fixtures/AI/fake-review-valid.json'));
            }
        };
        $this->app->instance(AIReviewProvider::class, $provider);

        $reviewRun = $this->createReviewRunWithSnapshot();

        $this->from(route('reviews.show', $reviewRun))
            ->put(route('review-instructions.update'), [
                'custom_instructions' => 'Prioritize authorization bypasses in the first pass.',
            ]);

        app(ReviewRunRepository::class)->queueForExecution($reviewRun);
        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertStringContainsString(
            'Prioritize authorization bypasses in the first pass.',
            $provider->requests[0]->instructions,
        );

        $this->post(route('reviews.drafts.generate', $reviewRun));

        $draft = ReviewCommentDraft::query()
            ->where('review_run_id', $reviewRun->id)
            ->firstOrFail();
        $originalDraftUpdatedAt = $draft->updated_at;
        $originalFindingCount = ReviewFinding::query()
            ->where('review_run_id', $reviewRun->id)
            ->count();

        $this->from(route('reviews.show', $reviewRun))
            ->put(route('review-instructions.update'), [
                'custom_instructions' => 'For retries, focus on unsafe deserialization.',
            ]);

        $draft->refresh();

        $this->assertTrue($originalDraftUpdatedAt->equalTo($draft->updated_at));
        $this->assertNull($draft->stale_at);
        $this->assertSame(
            $originalFindingCount,
            ReviewFinding::query()
                ->where('review_run_id', $reviewRun->id)
                ->count(),
        );

        app(ReviewRunRepository::class)->queueForExecution(ReviewRun::findOrFail($reviewRun->id));
        (new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

        $this->assertStringContainsString(
            'For retries, focus on unsafe deserialization.',
            $provider->requests[1]->instructions,
        );
        $this->assertStringNotContainsString(
            'Prioritize authorization bypasses in the first pass.',
            $provider->requests[1]->instructions,
        );
    }

    private function createReviewRunWithSnapshot(): ReviewRun
    {
        $reviewRun = app(ReviewRunService::class)
            ->createFromPullRequestUrl('https://github.com/laravel/framework/pull/1')
            ->reviewRun();

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
                new PullRequestFileSnapshot(
                    filename: 'app/Contracts/GitHub/GitHubClient.php',
                    patch: '@@ -1 +1 @@',
                    sha: '2222222222222222222222222222222222222222',
                ),
            ],
        );
    }
}
