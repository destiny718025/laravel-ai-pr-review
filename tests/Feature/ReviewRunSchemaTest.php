<?php

namespace Tests\Feature;

use App\Enums\ReviewRunStatus;
use App\Models\GitHubRepository;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRunSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_run_status_vocabulary_is_exact(): void
    {
        $this->assertSame(
            ['pending', 'queued', 'running', 'completed', 'failed', 'cancelled'],
            array_map(
                fn (ReviewRunStatus $status): string => $status->value,
                ReviewRunStatus::cases(),
            ),
        );
    }

    public function test_review_run_foundation_persists_identity_status_and_lifecycle_fields(): void
    {
        $repository = GitHubRepository::create([
            'owner' => 'Acme',
            'name' => 'ReviewBot',
            'full_name' => 'acme/reviewbot',
        ]);

        $pullRequest = $repository->pullRequests()->create([
            'number' => 42,
            'source_url' => 'https://github.com/Acme/ReviewBot/pull/42',
        ]);

        $reviewRun = $pullRequest->reviewRuns()->create([
            'status' => ReviewRunStatus::Failed,
            'safe_error_message' => 'Unable to prepare the review.',
            'queued_at' => '2026-06-27 09:00:00',
            'started_at' => '2026-06-27 09:01:00',
            'completed_at' => '2026-06-27 09:05:00',
            'failed_at' => '2026-06-27 09:05:00',
            'cancelled_at' => '2026-06-27 09:06:00',
        ]);

        $this->assertDatabaseHas('repositories', [
            'owner' => 'Acme',
            'name' => 'ReviewBot',
            'full_name' => 'acme/reviewbot',
        ]);

        $this->assertDatabaseHas('pull_requests', [
            'repository_id' => $repository->id,
            'number' => 42,
            'source_url' => 'https://github.com/Acme/ReviewBot/pull/42',
        ]);

        $this->assertDatabaseHas('review_runs', [
            'pull_request_id' => $pullRequest->id,
            'status' => 'failed',
            'safe_error_message' => 'Unable to prepare the review.',
        ]);

        $this->assertTrue($repository->pullRequests()->first()->is($pullRequest));
        $this->assertTrue($pullRequest->repository->is($repository));
        $this->assertTrue($pullRequest->reviewRuns()->first()->is($reviewRun));
        $this->assertTrue($reviewRun->pullRequest->is($pullRequest));

        $reviewRun->refresh();

        $this->assertSame(ReviewRunStatus::Failed, $reviewRun->status);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->queued_at);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->started_at);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->completed_at);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->failed_at);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->cancelled_at);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->created_at);
        $this->assertInstanceOf(DateTimeInterface::class, $reviewRun->updated_at);
    }
}
