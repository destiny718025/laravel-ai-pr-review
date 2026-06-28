<?php

namespace Tests\Feature;

use App\Enums\ReviewRunStatus;
use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewRun;
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRunHistoryAndDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_dashboard_lists_recent_review_runs_newest_first(): void
    {
        $service = app(ReviewRunService::class);

        $older = $service->createFromPullRequestUrl('https://github.com/old/repo/pull/10')->reviewRun();
        $newer = $service->createFromPullRequestUrl('https://github.com/new/repo/pull/20')->reviewRun();

        $older->forceFill(['created_at' => now()->subMinute()])->save();
        $newer->forceFill(['created_at' => now()])->save();

        $response = $this->get('/reviews')
            ->assertOk()
            ->assertSee('Recent Review Runs')
            ->assertSee('Pending')
            ->assertSee('new/repo')
            ->assertSee('old/repo')
            ->assertSee('PR #20')
            ->assertSee('https://github.com/new/repo/pull/20')
            ->assertSee('View review run');

        $response->assertSeeInOrder([
            'new/repo',
            'old/repo',
        ]);
    }

    public function test_review_detail_displays_identity_metadata_and_pending_summary(): void
    {
        $reviewRun = app(ReviewRunService::class)
            ->createFromPullRequestUrl('https://github.com/owner/repo/pull/123')
            ->reviewRun();

        $this->get('/reviews/'.$reviewRun->id)
            ->assertOk()
            ->assertSee('Back to review runs')
            ->assertSee('Review Run #'.$reviewRun->id)
            ->assertSee('Pending')
            ->assertSee('owner/repo')
            ->assertSee('PR #123')
            ->assertSee('https://github.com/owner/repo/pull/123')
            ->assertSee('Created')
            ->assertSee('Updated')
            ->assertSee('This review run is ready for the next processing step.');
    }

    public function test_failed_review_detail_displays_only_safe_error_copy_and_next_step(): void
    {
        $failed = $this->createReviewRun(
            status: ReviewRunStatus::Failed,
            safeErrorMessage: 'GitHub returned a safe validation error.',
        );

        $this->get('/reviews/'.$failed->id)
            ->assertOk()
            ->assertSee('Review run failed')
            ->assertSee('GitHub returned a safe validation error.')
            ->assertSee('Review the safe error summary, then run AI review again after fixing the source issue.');
    }

    public function test_failed_review_detail_uses_safe_fallback_when_no_summary_exists(): void
    {
        $failed = $this->createReviewRun(
            status: ReviewRunStatus::Failed,
            safeErrorMessage: null,
        );

        $this->get('/reviews/'.$failed->id)
            ->assertOk()
            ->assertSee('Review run failed')
            ->assertSee('The run failed, but no safe error summary was recorded.')
            ->assertSee('Review the safe error summary, then run AI review again after fixing the source issue.');
    }

    public function test_reserved_statuses_render_title_case_labels(): void
    {
        foreach ([
            ReviewRunStatus::Queued,
            ReviewRunStatus::Running,
            ReviewRunStatus::Completed,
            ReviewRunStatus::Failed,
            ReviewRunStatus::Cancelled,
        ] as $status) {
            $reviewRun = $this->createReviewRun(status: $status, repositoryName: $status->value);

            $this->get('/reviews/'.$reviewRun->id)
                ->assertOk()
                ->assertSee(str($status->value)->title());
        }
    }

    private function createReviewRun(
        ReviewRunStatus $status,
        ?string $safeErrorMessage = null,
        string $repositoryName = 'repo',
    ): ReviewRun {
        $repository = GitHubRepository::create([
            'owner' => 'owner',
            'name' => $repositoryName,
            'full_name' => 'owner/'.$repositoryName,
        ]);

        $pullRequest = PullRequest::create([
            'repository_id' => $repository->id,
            'number' => 123,
            'source_url' => 'https://github.com/owner/'.$repositoryName.'/pull/123',
        ]);

        return ReviewRun::create([
            'pull_request_id' => $pullRequest->id,
            'status' => $status,
            'safe_error_message' => $safeErrorMessage,
        ]);
    }
}
