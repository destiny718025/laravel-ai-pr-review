<?php

namespace Tests\Unit\AI;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\AI\HttpOpenAIReviewProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIReviewProviderTest extends TestCase
{
    public function test_openai_provider_resolves_only_when_config_enabled(): void
    {
        config([
            'services.openai.enabled' => true,
            'services.openai.base_url' => 'https://api.openai.test',
            'services.openai.api_key' => 'sk-test-secret',
            'services.openai.model' => 'gpt-test',
            'services.openai.timeout' => 5,
        ]);

        $this->assertInstanceOf(HttpOpenAIReviewProvider::class, app(AIReviewProvider::class));
    }

    public function test_fake_provider_remains_default_when_openai_disabled(): void
    {
        config(['services.openai.enabled' => false]);

        $this->assertInstanceOf(FakeAIReviewProvider::class, app(AIReviewProvider::class));
    }

    public function test_openai_provider_sends_http_fakeable_request_and_returns_raw_json_text(): void
    {
        config([
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
