<?php

namespace Tests\Unit\AI;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\AI\HttpOpenAIReviewProvider;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class OpenAIReviewProviderTest extends TestCase
{
    public function test_openai_api_key_provider_resolves_when_selector_requests_it(): void
    {
        config([
            'services.ai.provider' => 'openai_api_key',
            'services.openai.base_url' => 'https://api.openai.test',
            'services.openai.api_key' => 'sk-test-secret',
            'services.openai.model' => 'gpt-test',
            'services.openai.timeout' => 5,
        ]);

        $this->assertInstanceOf(HttpOpenAIReviewProvider::class, app(AIReviewProvider::class));
    }

    public function test_fake_provider_remains_default_when_selector_is_fake(): void
    {
        config(['services.ai.provider' => 'fake']);

        $this->assertInstanceOf(FakeAIReviewProvider::class, app(AIReviewProvider::class));
    }

    public function test_unsupported_ai_provider_selector_throws_instead_of_falling_back(): void
    {
        config(['services.ai.provider' => 'not-a-real-provider']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AI provider');

        app(AIReviewProvider::class);
    }

    public function test_openai_codex_oauth_selector_fails_closed_until_the_transport_is_installed(): void
    {
        config(['services.ai.provider' => 'openai_codex_oauth']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('openai_codex_oauth provider is not available');

        app(AIReviewProvider::class);
    }

    public function test_openai_provider_sends_http_fakeable_request_and_returns_raw_json_text(): void
    {
        config([
            'services.ai.provider' => 'openai_api_key',
            'services.openai.base_url' => 'https://api.openai.test',
            'services.openai.api_key' => 'sk-test-secret',
            'services.openai.model' => 'gpt-test',
            'services.openai.timeout' => 5,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'text' => '{"findings":[]}',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $json = app(HttpOpenAIReviewProvider::class)->review($this->request());

        $this->assertSame('{"findings":[]}', $json);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/responses'));
        Http::assertSentCount(1);
    }

    private function request(): AIReviewRequest
    {
        return new AIReviewRequest(
            repositoryFullName: 'laravel/framework',
            pullRequestNumber: 1,
            sourceUrl: 'https://github.com/laravel/framework/pull/1',
            headSha: 'abc123def4567890abc123def4567890abc12345',
            title: 'Add queued AI review',
            changedFiles: [
                [
                    'filename' => 'app/Example.php',
                    'patch' => '@@ -1 +1 @@',
                    'sha' => '1111111111111111111111111111111111111111',
                ],
            ],
            instructions: 'Review this PR.',
        );
    }
}
