<?php

namespace Tests\Feature;

use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDraftPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_detail_shows_separate_findings_and_comment_drafts_sections(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'severity' => 'high',
            'category' => 'bug',
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'title' => 'Unhandled malformed upstream payload',
            'rationale' => 'Malformed responses can cascade into runtime errors.',
            'suggested_comment_text' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);

        $response = $this->get(route('reviews.show', $reviewRun));

        $response
            ->assertOk()
            ->assertSeeInOrder(['Structured Findings', 'Comment Drafts'])
            ->assertSee('Unhandled malformed upstream payload')
            ->assertSee('Suggested comment: Please validate the provider response shape before consuming nested fields so malformed responses fail safely.')
            ->assertSee('Generate Drafts')
            ->assertSee(route('reviews.drafts.generate', $reviewRun), false)
            ->assertSee('No comment drafts have been generated for this run.')
            ->assertDontSee('Approve Draft')
            ->assertDontSee('Publish');
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
