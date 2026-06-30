<?php

namespace App\Providers;

use App\Contracts\AI\AIReviewProvider;
use App\Contracts\GitHub\GitHubClient;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\AI\HttpOpenAICodexOAuthReviewProvider;
use App\Services\AI\HttpOpenAIReviewProvider;
use App\Services\GitHub\HttpGitHubClient;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GitHubClient::class, HttpGitHubClient::class);
        $this->app->bind(AIReviewProvider::class, function () {
            return match (config('services.ai.provider', 'fake')) {
                'fake', null, '' => app(FakeAIReviewProvider::class),
                'openai_api_key' => app(HttpOpenAIReviewProvider::class),
                'openai_codex_oauth' => app(HttpOpenAICodexOAuthReviewProvider::class),
                default => throw new InvalidArgumentException('Unsupported AI provider selector configured.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
