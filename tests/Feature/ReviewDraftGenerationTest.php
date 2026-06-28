<?php

namespace Tests\Feature;

use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_drafts_creates_missing_drafts_from_current_findings_only(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $firstFinding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'title' => 'Unhandled malformed upstream payload',
            'suggested_comment_text' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);
        $secondFinding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/Contracts/GitHub/GitHubClient.php',
            'line_reference' => null,
            'title' => 'Map provider failures safely',
            'suggested_comment_text' => 'Please map provider exceptions to safe summaries before storing or rendering them.',
        ]);
        $supersededFinding = ReviewFinding::factory()->superseded()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/Legacy.php',
            'suggested_comment_text' => 'This historical finding should not create a new draft.',
        ]);

        $this->post(route('reviews.drafts.generate', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Generated 2 comment drafts.');

        $this->assertDatabaseHas('review_comment_drafts', [
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $firstFinding->id,
            'status' => 'draft',
            'body' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);
        $this->assertDatabaseHas('review_comment_drafts', [
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $secondFinding->id,
            'status' => 'draft',
            'body' => 'Please map provider exceptions to safe summaries before storing or rendering them.',
        ]);
        $this->assertDatabaseMissing('review_comment_drafts', [
            'source_review_finding_id' => $supersededFinding->id,
        ]);

        $this->post(route('reviews.drafts.generate', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Generated 0 comment drafts.');

        $this->assertSame(2, $reviewRun->drafts()->count());
    }

    private function createCompletedReviewRun(): ReviewRun
    {
        $repository = GitHubRepository::query()->create([
            'owner' => 'laravel',
            'name' => 'framework',
            'full_name' => 'laravel/framework',
        ]);

        $pullRequest = PullRequest::query()->create([
            'repository_id' => $repository->id,
            'number' => 1,
            'source_url' => 'https://github.com/laravel/framework/pull/1',
        ]);

        $reviewRun = ReviewRun::query()->create([
            'pull_request_id' => $pullRequest->id,
            'status' => 'completed',
            'github_title' => 'Add queued AI review',
            'github_state' => 'open',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'github_fetched_at' => now(),
            'completed_at' => now(),
        ]);

        $reviewRun->files()->createMany([
            [
                'filename' => 'app/Services/GitHub/HttpGitHubClient.php',
                'patch' => '@@ -1 +1 @@',
                'sha' => '1111111111111111111111111111111111111111',
            ],
            [
                'filename' => 'app/Contracts/GitHub/GitHubClient.php',
                'patch' => '@@ -1 +1 @@',
                'sha' => '2222222222222222222222222222222222222222',
            ],
        ]);

        return $reviewRun;
    }
}
