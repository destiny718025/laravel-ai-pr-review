<?php

namespace Tests\Feature;

use App\Enums\ReviewCommentDraftStatus;
use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewCommentDraft;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomReviewInstructionsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_instructions_are_stored_separately_from_findings_and_drafts(): void
    {
        $reviewRun = $this->createCompletedReviewRun();
        $finding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'suggested_comment_text' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);
        $draft = ReviewCommentDraft::factory()->create([
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $finding->id,
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Approved local draft text.',
            'stale_at' => now()->subMinute(),
        ]);

        $originalFinding = $finding->only([
            'severity',
            'category',
            'file_path',
            'line_reference',
            'title',
            'rationale',
            'suggested_comment_text',
            'superseded_at',
        ]);
        $originalDraftUpdatedAt = $draft->updated_at;

        $this->from(route('reviews.show', $reviewRun))
            ->put(route('review-instructions.update'), [
                'custom_instructions' => 'Prioritize authorization bugs and unsafe data exposure.',
            ])
            ->assertRedirect(route('reviews.show', $reviewRun));

        $this->assertTrue(Schema::hasTable('review_instruction_settings'));
        $this->assertFalse(Schema::hasColumn('review_findings', 'custom_instructions'));
        $this->assertFalse(Schema::hasColumn('review_comment_drafts', 'custom_instructions'));
        $this->assertDatabaseHas('review_instruction_settings', [
            'scope' => 'global',
            'custom_instructions' => 'Prioritize authorization bugs and unsafe data exposure.',
        ]);

        $finding->refresh();
        $draft->refresh();

        $this->assertSame($originalFinding, $finding->only(array_keys($originalFinding)));
        $this->assertSame(ReviewCommentDraftStatus::Approved, $draft->status);
        $this->assertSame('Approved local draft text.', $draft->body);
        $this->assertNotNull($draft->stale_at);
        $this->assertTrue($originalDraftUpdatedAt->equalTo($draft->updated_at));
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

        return ReviewRun::query()->create([
            'pull_request_id' => $pullRequest->id,
            'status' => 'completed',
            'github_title' => 'Add queued AI review',
            'github_state' => 'open',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'github_fetched_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
