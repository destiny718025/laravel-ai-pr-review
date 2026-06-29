<?php

namespace Tests\Feature;

use App\Enums\ReviewCommentDraftStatus;
use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewCommentDraft;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDraftWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_body_can_be_edited_while_status_is_draft(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft();

        $this->patch(route('reviews.drafts.update', [$reviewRun, $draft]), [
            'body' => 'Please add a null check before dereferencing this payload.',
        ])
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Comment draft updated.');

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'draft',
            'body' => 'Please add a null check before dereferencing this payload.',
        ]);
    }

    public function test_approved_draft_rejects_direct_edits_until_unapproved(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Approved local text.',
        ]);

        $this->patch(route('reviews.drafts.update', [$reviewRun, $draft]), [
            'body' => 'This edit should be rejected.',
        ])->assertForbidden();

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'approved',
            'body' => 'Approved local text.',
        ]);
    }

    public function test_posted_draft_rejects_direct_edits(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Posted,
            'body' => 'Posted local text.',
            'github_comment_id' => 'gh-posted',
            'github_comment_html_url' => 'https://github.com/example/repo/pull/1#discussion_r700',
            'posted_at' => now(),
        ]);

        $this->patch(route('reviews.drafts.update', [$reviewRun, $draft]), [
            'body' => 'This posted draft should stay locked.',
        ])->assertForbidden();

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'posted',
            'body' => 'Posted local text.',
        ]);
    }

    public function test_failed_draft_rejects_direct_edits(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Failed,
            'body' => 'Failed local text.',
            'publication_error_code' => 'rate_limited',
            'publication_error_message' => 'GitHub rate limit was reached. Try publishing comments again later.',
        ]);

        $this->patch(route('reviews.drafts.update', [$reviewRun, $draft]), [
            'body' => 'This failed draft should stay locked.',
        ])->assertForbidden();

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'failed',
            'body' => 'Failed local text.',
        ]);
    }

    public function test_selected_drafts_can_be_approved_locally_without_posting_to_github(): void
    {
        [$reviewRun, $firstDraft] = $this->createReviewRunWithDraft();
        [, $secondDraft] = $this->createReviewRunWithDraft([], $reviewRun);

        $this->post(route('reviews.drafts.approve', $reviewRun), [
            'draft_ids' => [$firstDraft->id, $secondDraft->id],
        ])
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Approved 2 comment drafts.');

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $firstDraft->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $secondDraft->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseMissing('review_comment_drafts', [
            'review_run_id' => $reviewRun->id,
            'status' => 'posted',
        ]);
    }

    public function test_cancel_approval_returns_an_approved_draft_to_draft(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Approved,
        ]);

        $this->post(route('reviews.drafts.unapprove', [$reviewRun, $draft]))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Comment draft returned to draft.');

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'draft',
        ]);
    }

    public function test_posted_draft_rejects_cancel_approval(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Posted,
            'github_comment_id' => 'gh-posted',
            'github_comment_html_url' => 'https://github.com/example/repo/pull/1#discussion_r701',
            'posted_at' => now(),
        ]);

        $this->post(route('reviews.drafts.unapprove', [$reviewRun, $draft]))
            ->assertForbidden();

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'posted',
        ]);
    }

    public function test_failed_draft_rejects_cancel_approval(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Failed,
            'publication_error_code' => 'server_unavailable',
            'publication_error_message' => 'GitHub could not be reached. Try publishing comments again later.',
        ]);

        $this->post(route('reviews.drafts.unapprove', [$reviewRun, $draft]))
            ->assertForbidden();

        $this->assertDatabaseHas('review_comment_drafts', [
            'id' => $draft->id,
            'status' => 'failed',
        ]);
    }

    public function test_review_detail_shows_edit_approval_and_stale_controls_without_per_draft_publish_selectors(): void
    {
        [$reviewRun, $draft] = $this->createReviewRunWithDraft([
            'stale_at' => now(),
        ]);
        [, $approvedDraft] = $this->createReviewRunWithDraft([
            'status' => ReviewCommentDraftStatus::Approved,
        ], $reviewRun);

        $this->get(route('reviews.show', $reviewRun))
            ->assertOk()
            ->assertSee(route('reviews.drafts.update', [$reviewRun, $draft]), false)
            ->assertSee(route('reviews.drafts.approve', $reviewRun), false)
            ->assertSee(route('reviews.drafts.unapprove', [$reviewRun, $approvedDraft]), false)
            ->assertSee('Stale Draft')
            ->assertSee('Approve Selected')
            ->assertSee('Cancel Approval')
            ->assertDontSee('Publish Selected')
            ->assertDontSee('name="publish_draft_id"', false);
    }

    /**
     * @param  array<string, mixed>  $draftOverrides
     * @return array{0: ReviewRun, 1: ReviewCommentDraft}
     */
    private function createReviewRunWithDraft(array $draftOverrides = [], ?ReviewRun $reviewRun = null): array
    {
        $reviewRun ??= $this->createCompletedReviewRun();

        $finding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
            'line_reference' => '24',
            'title' => 'Unhandled malformed upstream payload',
            'suggested_comment_text' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
        ]);

        $draft = ReviewCommentDraft::factory()->create([
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $finding->id,
            'body' => 'Please validate the provider response shape before consuming nested fields so malformed responses fail safely.',
            'file_path' => $finding->file_path,
            'line_reference' => $finding->line_reference,
            ...$draftOverrides,
        ]);

        return [$reviewRun, $draft];
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
