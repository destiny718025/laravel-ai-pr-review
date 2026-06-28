<?php

namespace Tests\Feature;

use App\Models\GitHubRepository;
use App\Models\PullRequest;
use App\Models\ReviewRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomReviewInstructionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_detail_page_renders_global_custom_review_instructions(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        DB::table('review_instruction_settings')->insert([
            'scope' => 'global',
            'custom_instructions' => 'Focus on security regressions and risky data handling.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('reviews.show', $reviewRun))
            ->assertOk()
            ->assertSee('Custom Review Instructions')
            ->assertSee('Focus on security regressions and risky data handling.')
            ->assertSee(route('review-instructions.update'), false);
    }

    public function test_custom_review_instructions_can_be_saved_with_isolated_validation_errors(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $this->from(route('reviews.show', $reviewRun))
            ->put(route('review-instructions.update'), [
                'custom_instructions' => str_repeat('x', 20001),
            ])
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHasErrors(['custom_instructions'], null, 'instructions')
            ->assertSessionDoesntHaveErrors(['body']);

        $this->from(route('reviews.show', $reviewRun))
            ->put(route('review-instructions.update'), [
                'custom_instructions' => 'Flag SQL injection risks and missing authorization checks.',
            ])
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Custom review instructions saved.');

        $this->assertDatabaseHas('review_instruction_settings', [
            'scope' => 'global',
            'custom_instructions' => 'Flag SQL injection risks and missing authorization checks.',
        ]);
    }

    public function test_blank_custom_review_instructions_are_normalized_to_null(): void
    {
        $reviewRun = $this->createCompletedReviewRun();

        $this->from(route('reviews.show', $reviewRun))
            ->put(route('review-instructions.update'), [
                'custom_instructions' => " \n ",
            ])
            ->assertRedirect(route('reviews.show', $reviewRun))
            ->assertSessionHas('status', 'Custom review instructions saved.');

        $this->assertDatabaseHas('review_instruction_settings', [
            'scope' => 'global',
            'custom_instructions' => null,
        ]);
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
