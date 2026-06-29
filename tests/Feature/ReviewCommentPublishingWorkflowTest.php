<?php

namespace Tests\Feature;

use App\Contracts\GitHub\GitHubClient;
use App\Data\GitHub\GitHubCommentPublicationResult;
use App\Data\GitHub\GitHubCommentPublicationTarget;
use App\Data\GitHub\PullRequestSnapshot;
use App\Enums\ReviewCommentDraftStatus;
use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewCommentDraft;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCommentPublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_detail_shows_publish_and_retry_actions_only_when_relevant_drafts_exist(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $postedAt = CarbonImmutable::parse('2026-06-29T12:34:00Z');

        $approvedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Publish this approved draft.',
        ]);

        $failedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Failed,
            'body' => 'Retry this failed draft.',
            'publication_error_code' => 'rate_limited',
            'publication_error_message' => 'GitHub rate limit was reached. Try publishing comments again later.',
        ]);

        $postedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Posted,
            'body' => 'Already posted draft.',
            'github_comment_id' => '404',
            'github_comment_html_url' => 'https://github.com/example/repo/pull/1#discussion_r404',
            'posted_at' => $postedAt,
        ]);

        $response = $this->get(route('reviews.show', $reviewRun));

        $response->assertOk()
            ->assertSee('Comment Drafts')
            ->assertSee('Publish Approved')
            ->assertSee(route('reviews.drafts.publish-approved', $reviewRun), false)
            ->assertSee('Retry Failed')
            ->assertSee(route('reviews.drafts.retry-failed', $reviewRun), false)
            ->assertSee('Posted')
            ->assertSee($postedAt->format('Y-m-d H:i'))
            ->assertSee($postedDraft->github_comment_html_url, false)
            ->assertSee('GitHub rate limit was reached. Try publishing comments again later.')
            ->assertDontSee(route('reviews.drafts.update', [$reviewRun, $postedDraft]), false)
            ->assertDontSee(route('reviews.drafts.unapprove', [$reviewRun, $postedDraft]), false)
            ->assertDontSee(route('reviews.drafts.update', [$reviewRun, $failedDraft]), false)
            ->assertDontSee(route('reviews.drafts.unapprove', [$reviewRun, $failedDraft]), false)
            ->assertSee(route('reviews.drafts.unapprove', [$reviewRun, $approvedDraft]), false);
    }

    public function test_review_detail_hides_publish_and_retry_actions_when_no_relevant_drafts_exist(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Draft,
            'body' => 'Still editable.',
        ]);

        $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Posted,
            'body' => 'Already posted.',
            'github_comment_id' => '405',
            'github_comment_html_url' => 'https://github.com/example/repo/pull/1#discussion_r405',
            'posted_at' => CarbonImmutable::parse('2026-06-29T12:35:00Z'),
        ]);

        $this->get(route('reviews.show', $reviewRun))
            ->assertOk()
            ->assertDontSee('Publish Approved')
            ->assertDontSee('Retry Failed');
    }

    public function test_publish_approved_route_redirects_back_with_summary_and_publishes_only_approved_drafts(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $approvedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Approved for publication.',
            'file_path' => 'app/Services/ReviewCommentPublishingService.php',
            'line_reference' => '17',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
        ]);

        $draftDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Draft,
            'body' => 'Do not publish me.',
        ]);

        $failedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Failed,
            'body' => 'Retry me later.',
            'publication_error_code' => 'server_unavailable',
            'publication_error_message' => 'GitHub could not be reached. Try publishing comments again later.',
        ]);

        $client = new FakeWorkflowPublishingGitHubClient;
        $client->reviewCommentResults[] = GitHubCommentPublicationResult::fromGitHubPayload([
            'id' => 501,
            'html_url' => 'https://github.com/example/repo/pull/1#discussion_r501',
            'created_at' => '2026-06-29T12:40:00Z',
        ]);

        $this->app->instance(GitHubClient::class, $client);

        $this->post(route('reviews.drafts.publish-approved', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Published 1 approved comment drafts. 0 failed.');

        $approvedDraft->refresh();
        $draftDraft->refresh();
        $failedDraft->refresh();

        $this->assertCount(1, $client->reviewCommentTargets);
        $this->assertSame($approvedDraft->body, $client->reviewCommentTargets[0]->body);
        $this->assertSame('posted', $approvedDraft->status->value);
        $this->assertSame('draft', $draftDraft->status->value);
        $this->assertSame('failed', $failedDraft->status->value);
    }

    public function test_retry_failed_route_redirects_back_with_summary_and_retries_only_failed_drafts(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $failedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Failed,
            'body' => 'Retry this failed draft.',
            'file_path' => 'app/Services/ReviewCommentPublishingService.php',
            'line_reference' => '33',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'publication_error_code' => 'target_invalid',
            'publication_error_message' => 'GitHub could not place the comment on the requested line. Review the diff context and try again.',
        ]);

        $approvedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Keep approved until publish-all is clicked.',
        ]);

        $client = new FakeWorkflowPublishingGitHubClient;
        $client->reviewCommentResults[] = GitHubCommentPublicationResult::fromGitHubPayload([
            'id' => 601,
            'html_url' => 'https://github.com/example/repo/pull/1#discussion_r601',
            'created_at' => '2026-06-29T12:45:00Z',
        ]);

        $this->app->instance(GitHubClient::class, $client);

        $this->post(route('reviews.drafts.retry-failed', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Retried 1 failed comment drafts. 1 published, 0 failed.');

        $failedDraft->refresh();
        $approvedDraft->refresh();

        $this->assertCount(1, $client->reviewCommentTargets);
        $this->assertSame($failedDraft->body, $client->reviewCommentTargets[0]->body);
        $this->assertSame('posted', $failedDraft->status->value);
        $this->assertSame('approved', $approvedDraft->status->value);
    }

    /**
     * @param  array<string, mixed>  $draftOverrides
     */
    private function createDraft(ReviewRun $reviewRun, array $draftOverrides = []): ReviewCommentDraft
    {
        $finding = ReviewFinding::factory()->current()->create([
            'review_run_id' => $reviewRun->id,
            'file_path' => $draftOverrides['file_path'] ?? 'app/Example.php',
            'line_reference' => $draftOverrides['line_reference'] ?? '42',
            'title' => 'Example finding',
            'suggested_comment_text' => $draftOverrides['body'] ?? 'Please guard this edge case before merging.',
        ]);

        return ReviewCommentDraft::factory()->create([
            'review_run_id' => $reviewRun->id,
            'source_review_finding_id' => $finding->id,
            ...$draftOverrides,
        ]);
    }

    private function createCompletedReviewRun(): ReviewRun
    {
        $repository = GitHubRepository::query()->create([
            'owner' => 'example',
            'name' => 'repo',
            'full_name' => 'example/repo',
        ]);

        $pullRequest = PullRequest::query()->create([
            'repository_id' => $repository->id,
            'number' => 1,
            'source_url' => 'https://github.com/example/repo/pull/1',
        ]);

        return ReviewRun::query()->create([
            'pull_request_id' => $pullRequest->id,
            'status' => 'completed',
            'github_title' => 'Publish approved comment drafts',
            'github_state' => 'open',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'github_fetched_at' => now(),
            'completed_at' => now(),
        ]);
    }
}

class FakeWorkflowPublishingGitHubClient implements GitHubClient
{
    /**
     * @var array<int, GitHubCommentPublicationTarget>
     */
    public array $reviewCommentTargets = [];

    /**
     * @var array<int, GitHubCommentPublicationResult>
     */
    public array $reviewCommentResults = [];

    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot
    {
        throw new \BadMethodCallException('Not used in this test.');
    }

    public function listPullRequestFiles(string $owner, string $repository, int $pullRequestNumber): array
    {
        throw new \BadMethodCallException('Not used in this test.');
    }

    public function createPullRequestReviewComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult
    {
        $this->reviewCommentTargets[] = $target;

        if ($this->reviewCommentResults === []) {
            throw new \RuntimeException('No fake review comment result configured.');
        }

        return array_shift($this->reviewCommentResults);
    }

    public function createPullRequestIssueComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult
    {
        throw new \BadMethodCallException('Not used in this test.');
    }
}
