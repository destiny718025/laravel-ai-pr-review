<?php

namespace Tests\Feature;

use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewDraftPersistenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_run_loads_current_and_superseded_findings_alongside_persisted_drafts(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $currentFinding = ReviewFinding::query()->create([
            'review_run_id' => $reviewRun->id,
            'severity' => 'high',
            'category' => 'bug',
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'title' => 'Unhandled malformed upstream payload',
            'rationale' => 'Malformed responses can cascade into runtime errors.',
            'suggested_comment_text' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);

        $supersededFinding = ReviewFinding::query()->forceCreate([
            'review_run_id' => $reviewRun->id,
            'severity' => 'medium',
            'category' => 'security',
            'file_path' => 'app/Contracts/GitHub/GitHubClient.php',
            'line_reference' => null,
            'title' => 'Historical finding kept for draft provenance',
            'rationale' => 'Retries must preserve source history for already-created drafts.',
            'suggested_comment_text' => 'Please retain provenance for stale drafts during retries.',
            'superseded_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinute(),
        ]);

        DB::table('review_comment_drafts')->insert([
            [
                'review_run_id' => $reviewRun->id,
                'source_review_finding_id' => $currentFinding->id,
                'status' => 'draft',
                'body' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
                'file_path' => $currentFinding->file_path,
                'line_reference' => $currentFinding->line_reference,
                'github_head_sha' => (string) $reviewRun->github_head_sha,
                'source_file_sha' => '1111111111111111111111111111111111111111',
                'stale_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'review_run_id' => $reviewRun->id,
                'source_review_finding_id' => $supersededFinding->id,
                'status' => 'approved',
                'body' => 'Please retain provenance for stale drafts during retries.',
                'file_path' => $supersededFinding->file_path,
                'line_reference' => null,
                'github_head_sha' => (string) $reviewRun->github_head_sha,
                'source_file_sha' => null,
                'stale_at' => now()->subSecond(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subSecond(),
            ],
        ]);

        $reviewRun = ReviewRun::query()
            ->with(['currentFindings', 'findings', 'drafts.sourceFinding'])
            ->findOrFail($reviewRun->id);

        $this->assertCount(1, $reviewRun->currentFindings);
        $this->assertSame($currentFinding->id, $reviewRun->currentFindings->sole()->id);
        $this->assertCount(2, $reviewRun->findings);
        $this->assertTrue($reviewRun->findings->contains('id', $supersededFinding->id));
        $this->assertCount(2, $reviewRun->drafts);

        $activeDraft = $reviewRun->drafts->firstWhere('source_review_finding_id', $currentFinding->id);
        $staleDraft = $reviewRun->drafts->firstWhere('source_review_finding_id', $supersededFinding->id);

        $this->assertNotNull($activeDraft);
        $this->assertSame($currentFinding->id, $activeDraft->sourceFinding->id);
        $this->assertSame('app/Services/GitHub/HttpGitHubClient.php', $activeDraft->file_path);
        $this->assertSame('24', $activeDraft->line_reference);
        $this->assertSame((string) $reviewRun->github_head_sha, $activeDraft->github_head_sha);
        $this->assertSame('1111111111111111111111111111111111111111', $activeDraft->source_file_sha);
        $this->assertSame('draft', $activeDraft->status->value);
        $this->assertNull($activeDraft->stale_at);

        $this->assertNotNull($staleDraft);
        $this->assertSame($supersededFinding->id, $staleDraft->sourceFinding->id);
        $this->assertSame('app/Contracts/GitHub/GitHubClient.php', $staleDraft->file_path);
        $this->assertNull($staleDraft->line_reference);
        $this->assertSame((string) $reviewRun->github_head_sha, $staleDraft->github_head_sha);
        $this->assertNull($staleDraft->source_file_sha);
        $this->assertSame('approved', $staleDraft->status->value);
        $this->assertNotNull($staleDraft->stale_at);

        $currentFinding = ReviewFinding::query()
            ->with('sourceDrafts')
            ->findOrFail($currentFinding->id);

        $this->assertCount(1, $currentFinding->sourceDrafts);
        $this->assertSame($activeDraft->id, $currentFinding->sourceDrafts->sole()->id);
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
            'completed_at' => now(),
        ]);
    }
}
