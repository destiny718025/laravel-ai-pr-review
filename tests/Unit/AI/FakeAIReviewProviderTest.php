<?php

namespace Tests\Unit\AI;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\AI\ReviewInstructionBuilder;
use Tests\TestCase;

class FakeAIReviewProviderTest extends TestCase
{
    public function test_container_resolves_fake_provider_by_default(): void
    {
        config(['services.openai.enabled' => false]);

        $this->assertInstanceOf(FakeAIReviewProvider::class, app(AIReviewProvider::class));
    }

    public function test_fake_provider_returns_deterministic_fixture_json(): void
    {
        $json = app(AIReviewProvider::class)->review($this->request());

        $this->assertJson($json);
        $this->assertStringContainsString('Unhandled malformed upstream payload', $json);
    }

    public function test_fake_provider_can_use_invalid_fixture_for_failure_tests(): void
    {
        $json = (new FakeAIReviewProvider(base_path('tests/Fixtures/AI/fake-review-invalid.json')))
            ->review($this->request());

        $this->assertJson($json);
        $this->assertStringContainsString('"severity": "urgent"', $json);
    }

    public function test_default_review_instructions_encode_locked_vocabulary(): void
    {
        $instructions = app(ReviewInstructionBuilder::class)->buildDefault();

        foreach (['bug', 'security', 'performance', 'maintainability', 'style'] as $category) {
            $this->assertStringContainsString($category, $instructions);
        }

        foreach (['critical', 'high', 'medium', 'low'] as $severity) {
            $this->assertStringContainsString($severity, $instructions);
        }

        $this->assertStringContainsString('Prioritize bug and security findings first.', $instructions);
        $this->assertStringContainsString('Do not include comment draft state', $instructions);
    }

    private function request(): AIReviewRequest
    {
        return new AIReviewRequest(
            repositoryFullName: 'laravel/framework',
            pullRequestNumber: 1,
            sourceUrl: 'https://github.com/laravel/framework/pull/1',
            headSha: 'abc123def4567890abc123def4567890abc12345',
            title: 'Add queued AI review',
            changedFiles: [],
            instructions: 'Review the PR.',
        );
    }
}
