<?php

namespace Tests\Feature;

use App\Enums\ReviewRunStatus;
use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewRun;
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRunCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_review_run_from_a_valid_github_pull_request_url(): void
    {
        $result = app(ReviewRunService::class)->createFromPullRequestUrl(
            'https://github.com/Owner/Repo/pull/123?diff=split#discussion',
        );

        $this->assertTrue($result->successful());
        $this->assertNull($result->errorCode());
        $this->assertNotNull($result->reviewRun());

        $this->assertDatabaseHas('repositories', [
            'owner' => 'Owner',
            'name' => 'Repo',
            'full_name' => 'owner/repo',
        ]);

        $repository = GitHubRepository::firstOrFail();

        $this->assertDatabaseHas('pull_requests', [
            'repository_id' => $repository->id,
            'number' => 123,
            'source_url' => 'https://github.com/Owner/Repo/pull/123',
        ]);

        $pullRequest = PullRequest::firstOrFail();

        $this->assertDatabaseHas('review_runs', [
            'pull_request_id' => $pullRequest->id,
            'status' => ReviewRunStatus::Pending->value,
        ]);

        $this->assertSame(ReviewRunStatus::Pending, $result->reviewRun()->status);
        $this->assertTrue($result->reviewRun()->pullRequest->is($pullRequest));
    }

    public function test_duplicate_submissions_reuse_repository_and_pull_request_but_create_new_review_runs(): void
    {
        $service = app(ReviewRunService::class);

        $first = $service->createFromPullRequestUrl('https://github.com/Owner/Repo/pull/123');
        $second = $service->createFromPullRequestUrl('https://github.com/owner/repo/pull/123');

        $this->assertTrue($first->successful());
        $this->assertTrue($second->successful());
        $this->assertDatabaseCount('repositories', 1);
        $this->assertDatabaseCount('pull_requests', 1);
        $this->assertDatabaseCount('review_runs', 2);
        $this->assertTrue($first->reviewRun()->pullRequest->is($second->reviewRun()->pullRequest));
    }

    public function test_invalid_pull_request_urls_return_stable_error_codes_without_creating_records(): void
    {
        $service = app(ReviewRunService::class);

        $cases = [
            'not a url' => 'invalid_url',
            'https://example.com/owner/repo/pull/123' => 'not_github_pr_url',
            'https://github.com/owner/repo/issues/123' => 'not_github_pr_url',
            'https://github.com/owner/repo/pull' => 'missing_pr_number',
            'https://github.com/owner/repo/pull/not-a-number' => 'missing_pr_number',
            'https://github.com/owner/repo/pull/0' => 'missing_pr_number',
        ];

        foreach ($cases as $url => $expectedCode) {
            $result = $service->createFromPullRequestUrl($url);

            $this->assertFalse($result->successful(), $url);
            $this->assertNull($result->reviewRun(), $url);
            $this->assertSame($expectedCode, $result->errorCode(), $url);
            $this->assertNotSame('', $result->message(), $url);
        }

        $this->assertDatabaseCount('repositories', 0);
        $this->assertDatabaseCount('pull_requests', 0);
        $this->assertDatabaseCount('review_runs', 0);
    }
}
