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
use App\Services\ReviewCommentPublishingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCommentPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_approved_processes_only_approved_drafts_and_keeps_prior_success_when_a_later_draft_fails(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $lineLevelDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Line-level approved draft.',
            'file_path' => 'app/Services/PublishService.php',
            'line_reference' => '17',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
        ]);

        $fallbackDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Fallback approved draft.',
            'file_path' => 'app/Http/Controllers/ReviewController.php',
            'line_reference' => null,
        ]);

        $draftStatusDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Draft,
            'body' => 'Still a draft.',
        ]);

        $failedStatusDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Failed,
            'body' => 'Already failed draft.',
            'publication_error_code' => 'server_unavailable',
            'publication_error_message' => 'GitHub could not be reached. Try publishing comments again later.',
        ]);

        $postedStatusDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Posted,
            'body' => 'Already posted draft.',
            'github_comment_id' => 'gh-existing',
            'github_comment_html_url' => 'https://github.com/example/repo/pull/1#discussion_r1',
            'posted_at' => CarbonImmutable::parse('2026-06-29T01:00:00Z'),
        ]);

        $client = new FakePublishingGitHubClient;
        $client->reviewCommentResults[] = GitHubCommentPublicationResult::fromGitHubPayload([
            'id' => 101,
            'html_url' => 'https://github.com/example/repo/pull/1#discussion_r101',
            'created_at' => '2026-06-29T01:02:03Z',
        ]);
        $client->issueCommentFailures[] = new \RuntimeException('fallback issue comment failed');

        $this->app->instance(GitHubClient::class, $client);

        $result = app(ReviewCommentPublishingService::class)->publishApproved($reviewRun->id);

        $this->assertSame(2, $result->attemptedCount);
        $this->assertSame(1, $result->publishedCount);
        $this->assertSame(1, $result->failedCount);

        $lineLevelDraft->refresh();
        $fallbackDraft->refresh();
        $draftStatusDraft->refresh();
        $failedStatusDraft->refresh();
        $postedStatusDraft->refresh();

        $this->assertCount(1, $client->reviewCommentTargets);
        $this->assertSame($lineLevelDraft->body, $client->reviewCommentTargets[0]->body);
        $this->assertSame($lineLevelDraft->file_path, $client->reviewCommentTargets[0]->path);
        $this->assertSame((int) $lineLevelDraft->line_reference, $client->reviewCommentTargets[0]->line);
        $this->assertCount(1, $client->issueCommentTargets);
        $this->assertSame($fallbackDraft->body, $client->issueCommentTargets[0]->body);

        $this->assertSame('posted', $lineLevelDraft->status->value);
        $this->assertSame('101', $lineLevelDraft->github_comment_id);
        $this->assertSame('https://github.com/example/repo/pull/1#discussion_r101', $lineLevelDraft->github_comment_html_url);
        $this->assertNotNull($lineLevelDraft->posted_at);
        $this->assertNull($lineLevelDraft->publication_error_code);
        $this->assertNull($lineLevelDraft->publication_error_message);

        $this->assertSame('failed', $fallbackDraft->status->value);
        $this->assertNull($fallbackDraft->github_comment_id);
        $this->assertNull($fallbackDraft->github_comment_html_url);
        $this->assertNull($fallbackDraft->posted_at);
        $this->assertSame('server_unavailable', $fallbackDraft->publication_error_code);
        $this->assertSame('GitHub could not be reached. Try publishing comments again later.', $fallbackDraft->publication_error_message);
        $this->assertStringNotContainsString('fallback issue comment failed', (string) $fallbackDraft->publication_error_message);

        $this->assertSame('draft', $draftStatusDraft->status->value);
        $this->assertSame('failed', $failedStatusDraft->status->value);
        $this->assertSame('posted', $postedStatusDraft->status->value);
        $this->assertSame('gh-existing', $postedStatusDraft->github_comment_id);
    }

    public function test_retry_failed_processes_only_failed_drafts_and_overwrites_prior_failure_with_success(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $retryableDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Failed,
            'body' => 'Retry me.',
            'publication_error_code' => 'rate_limited',
            'publication_error_message' => 'GitHub rate limit was reached. Try publishing comments again later.',
        ]);

        $approvedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Do not retry approved drafts here.',
        ]);

        $postedDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Posted,
            'body' => 'Already posted.',
            'github_comment_id' => 'gh-posted',
            'github_comment_html_url' => 'https://github.com/example/repo/pull/1#discussion_r33',
            'posted_at' => CarbonImmutable::parse('2026-06-29T02:00:00Z'),
        ]);

        $client = new FakePublishingGitHubClient;
        $client->reviewCommentResults[] = GitHubCommentPublicationResult::fromGitHubPayload([
            'id' => 303,
            'html_url' => 'https://github.com/example/repo/pull/1#discussion_r303',
            'created_at' => '2026-06-29T02:03:04Z',
        ]);

        $this->app->instance(GitHubClient::class, $client);

        $result = app(ReviewCommentPublishingService::class)->retryFailed($reviewRun->id);

        $this->assertSame(1, $result->attemptedCount);
        $this->assertSame(1, $result->publishedCount);
        $this->assertSame(0, $result->failedCount);

        $retryableDraft->refresh();
        $approvedDraft->refresh();
        $postedDraft->refresh();

        $this->assertCount(1, $client->reviewCommentTargets);
        $this->assertSame($retryableDraft->body, $client->reviewCommentTargets[0]->body);
        $this->assertCount(0, $client->issueCommentTargets);

        $this->assertSame('posted', $retryableDraft->status->value);
        $this->assertSame('303', $retryableDraft->github_comment_id);
        $this->assertSame('https://github.com/example/repo/pull/1#discussion_r303', $retryableDraft->github_comment_html_url);
        $this->assertNotNull($retryableDraft->posted_at);
        $this->assertNull($retryableDraft->publication_error_code);
        $this->assertNull($retryableDraft->publication_error_message);

        $this->assertSame('approved', $approvedDraft->status->value);
        $this->assertSame('posted', $postedDraft->status->value);
        $this->assertSame('gh-posted', $postedDraft->github_comment_id);
    }

    public function test_publish_approved_uses_issue_comment_fallback_when_target_metadata_is_insufficient(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $fallbackDraft = $this->createDraft($reviewRun, [
            'status' => ReviewCommentDraftStatus::Approved,
            'body' => 'Fallback because line target is incomplete.',
            'file_path' => 'app/Console/Commands/ExampleCommand.php',
            'line_reference' => null,
        ]);

        $client = new FakePublishingGitHubClient;
        $client->issueCommentResults[] = GitHubCommentPublicationResult::fromGitHubPayload([
            'id' => 202,
            'html_url' => 'https://github.com/example/repo/pull/1#issuecomment-202',
            'created_at' => '2026-06-29T01:05:06Z',
        ]);

        $this->app->instance(GitHubClient::class, $client);

        app(ReviewCommentPublishingService::class)->publishApproved($reviewRun->id);

        $this->assertCount(0, $client->reviewCommentTargets);
        $this->assertCount(1, $client->issueCommentTargets);
        $this->assertSame($fallbackDraft->body, $client->issueCommentTargets[0]->body);
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
            'title' => 'Example review finding',
            'suggested_comment_text' => $draftOverrides['body'] ?? 'Please handle this edge case before merging.',
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

class FakePublishingGitHubClient implements GitHubClient
{
    /**
     * @var array<int, GitHubCommentPublicationTarget>
     */
    public array $reviewCommentTargets = [];

    /**
     * @var array<int, GitHubCommentPublicationTarget>
     */
    public array $issueCommentTargets = [];

    /**
     * @var array<int, GitHubCommentPublicationResult>
     */
    public array $reviewCommentResults = [];

    /**
     * @var array<int, GitHubCommentPublicationResult>
     */
    public array $issueCommentResults = [];

    /**
     * @var array<int, \Throwable>
     */
    public array $reviewCommentFailures = [];

    /**
     * @var array<int, \Throwable>
     */
    public array $issueCommentFailures = [];

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

        if ($this->reviewCommentFailures !== []) {
            throw array_shift($this->reviewCommentFailures);
        }

        if ($this->reviewCommentResults === []) {
            throw new \RuntimeException('No fake review comment result configured.');
        }

        return array_shift($this->reviewCommentResults);
    }

    public function createPullRequestIssueComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult
    {
        $this->issueCommentTargets[] = $target;

        if ($this->issueCommentFailures !== []) {
            throw array_shift($this->issueCommentFailures);
        }

        if ($this->issueCommentResults === []) {
            throw new \RuntimeException('No fake issue comment result configured.');
        }

        return array_shift($this->issueCommentResults);
    }
}
