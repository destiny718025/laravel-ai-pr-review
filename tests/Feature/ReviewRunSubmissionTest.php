<?php

namespace Tests\Feature;

use App\Enums\ReviewRunStatus;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRunSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_dashboard_is_available_without_authentication(): void
    {
        $this->get('/reviews')
            ->assertOk()
            ->assertSee('Laravel AI PR Review')
            ->assertSee('Review Runs')
            ->assertSee('Create a Review Run')
            ->assertSee('GitHub Pull Request URL')
            ->assertSee('Create Review Run')
            ->assertSee('Recent Review Runs');
    }

    public function test_valid_pull_request_url_creates_pending_review_run_and_redirects_to_detail(): void
    {
        $response = $this->post('/reviews', [
            'pr_url' => 'https://github.com/owner/repo/pull/123',
        ]);

        $reviewRun = ReviewRun::firstOrFail();

        $response
            ->assertRedirect('/reviews/'.$reviewRun->id)
            ->assertSessionHas('status', 'Review run created.');

        $this->assertSame(ReviewRunStatus::Pending, $reviewRun->status);
        $this->assertDatabaseCount('repositories', 1);
        $this->assertDatabaseCount('pull_requests', 1);
        $this->assertDatabaseCount('review_runs', 1);

        $this->followingRedirects()
            ->get('/reviews/'.$reviewRun->id)
            ->assertOk()
            ->assertSee('Review Run #'.$reviewRun->id);
    }

    public function test_invalid_service_errors_redirect_to_dashboard_without_creating_records(): void
    {
        $cases = [
            'not a url' => 'invalid_url',
            'https://example.com/owner/repo/pull/123' => 'not_github_pr_url',
            'https://github.com/owner/repo/pull/not-a-number' => 'missing_pr_number',
        ];

        foreach ($cases as $url => $expectedCode) {
            $this->post('/reviews', ['pr_url' => $url])
                ->assertRedirect('/reviews')
                ->assertSessionHas('service_error_code', $expectedCode)
                ->assertSessionHas('service_error_message');
        }

        $this->assertDatabaseCount('repositories', 0);
        $this->assertDatabaseCount('pull_requests', 0);
        $this->assertDatabaseCount('review_runs', 0);
    }
}
