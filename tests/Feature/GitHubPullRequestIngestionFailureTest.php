<?php

namespace Tests\Feature;

use App\Enums\ReviewRunStatus;
use App\Models\ReviewRun;
use App\Services\ReviewRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubPullRequestIngestionFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_or_unreadable_failure_marks_review_run_failed_with_safe_message(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1' => Http::response([
                'message' => 'Not Found: raw upstream body',
            ], 404),
        ]);

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'not_found_or_unreadable')
            ->assertSessionHas('service_error_message', 'GitHub could not find or read this pull request.');

        $this->assertFailedSafely($reviewRun, 'GitHub could not find or read this pull request.');
    }

    public function test_rate_limit_failure_marks_review_run_failed_with_safe_message(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1' => Http::response([
                'message' => 'API rate limit exceeded for raw token',
            ], 403, ['X-RateLimit-Remaining' => '0']),
        ]);

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'rate_limited');

        $this->assertFailedSafely($reviewRun, 'GitHub rate limit was reached. Try fetching this pull request again later.');
    }

    public function test_auth_failure_marks_review_run_failed_without_persisting_token(): void
    {
        config(['services.github.token' => 'ghp_secret_token_value']);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1' => Http::response([
                'message' => 'Bad credentials for ghp_secret_token_value',
            ], 401),
        ]);

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'auth_failed');

        $this->assertFailedSafely($reviewRun, 'GitHub rejected the configured token. Check the token before trying again.');
    }

    public function test_upstream_connection_failure_marks_review_run_failed_with_safe_message(): void
    {
        Http::preventStrayRequests();
        Http::fake(function () {
            throw new ConnectionException('Could not connect with raw request details');
        });

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'server_unavailable');

        $this->assertFailedSafely($reviewRun, 'GitHub could not be reached. Try fetching this pull request again later.');
    }

    public function test_malformed_response_marks_review_run_failed_with_safe_message(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1' => Http::response([
                'title' => 'Missing required head metadata',
                'state' => 'open',
            ], 200),
        ]);

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'malformed_response');

        $this->assertFailedSafely($reviewRun, 'GitHub returned an unexpected response. Try again later.');
    }

    public function test_successful_retry_clears_prior_github_failure_state(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1' => Http::sequence()
                ->push(['message' => 'Not Found: raw upstream body'], 404)
                ->push($this->fixture('GitHub/pull-request.json'), 200),
            'https://api.github.com/repos/laravel/framework/pulls/1/files?per_page=100&page=1' => Http::response(
                $this->fixture('GitHub/pull-request-files-page-1.json'),
                200,
                [
                    'Content-Type' => 'application/json',
                    'Link' => '<https://api.github.com/repos/laravel/framework/pulls/1/files?per_page=100&page=2>; rel="next", <https://api.github.com/repos/laravel/framework/pulls/1/files?per_page=100&page=2>; rel="last"',
                ],
            ),
            'https://api.github.com/repos/laravel/framework/pulls/1/files?per_page=100&page=2' => Http::response(
                $this->fixture('GitHub/pull-request-files-page-2.json'),
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $reviewRun = $this->createReviewRun();

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('service_error_code', 'not_found_or_unreadable');

        $this->assertFailedSafely($reviewRun, 'GitHub could not find or read this pull request.');

        $this->post(route('reviews.fetch', $reviewRun))
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'GitHub pull request data fetched.');

        $reviewRun = ReviewRun::query()->with('files')->findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Pending, $reviewRun->status);
        $this->assertNull($reviewRun->safe_error_message);
        $this->assertNull($reviewRun->failed_at);
        $this->assertSame('Add fixture-driven GitHub ingestion boundary', $reviewRun->github_title);
        $this->assertCount(3, $reviewRun->files);
    }

    private function createReviewRun(): ReviewRun
    {
        return app(ReviewRunService::class)
            ->createFromPullRequestUrl('https://github.com/laravel/framework/pull/1')
            ->reviewRun();
    }

    private function assertFailedSafely(ReviewRun $reviewRun, string $expectedMessage): void
    {
        $reviewRun = ReviewRun::findOrFail($reviewRun->id);

        $this->assertSame(ReviewRunStatus::Failed, $reviewRun->status);
        $this->assertSame($expectedMessage, $reviewRun->safe_error_message);
        $this->assertNotNull($reviewRun->failed_at);
        $this->assertNull($reviewRun->github_title);
        $this->assertNull($reviewRun->github_head_sha);

        $unsafeFragments = [
            'raw upstream body',
            'Authorization',
            'ghp_secret_token_value',
            'raw request details',
            'raw payload',
        ];

        foreach ($unsafeFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, (string) $reviewRun->safe_error_message);
        }
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/'.$path));
    }
}
