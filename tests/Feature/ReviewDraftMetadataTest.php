<?php

namespace Tests\Feature;

use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDraftMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_drafts_preserve_source_and_targeting_metadata(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $finding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'suggested_comment_text' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);

        $this->post(route('reviews.drafts.generate', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun));

        $this->assertDatabaseHas('review_comment_drafts', [
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $finding->id,
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'source_file_sha' => '1111111111111111111111111111111111111111',
            'stale_at' => null,
        ]);
    }

    public function test_generated_drafts_allow_missing_file_snapshot_sha(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $finding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/NoSnapshot.php',
            'line_reference' => null,
            'suggested_comment_text' => 'Please handle this file even if GitHub did not return a matching file snapshot.',
        ]);

        $this->post(route('reviews.drafts.generate', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun));

        $this->assertDatabaseHas('review_comment_drafts', [
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $finding->id,
            'file_path' => 'app/NoSnapshot.php',
            'line_reference' => null,
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'source_file_sha' => null,
        ]);
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

        $reviewRun->files()->create([
            'filename' => 'app/Services/GitHub/HttpGitHubClient.php',
            'patch' => '@@ -1 +1 @@',
            'sha' => '1111111111111111111111111111111111111111',
        ]);

        return $reviewRun;
    }
}
