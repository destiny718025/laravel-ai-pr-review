<?php

namespace App\Providers;

use App\Contracts\AI\AIReviewProvider;
use App\Contracts\GitHub\GitHubClient;
use App\Services\AI\FakeAIReviewProvider;
use App\Services\AI\HttpOpenAIReviewProvider;
use App\Services\GitHub\HttpGitHubClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GitHubClient::class, HttpGitHubClient::class);
        $this->app->bind(AIReviewProvider::class, function () {
            if ((bool) config('services.openai.enabled', false)) {
                return app(HttpOpenAIReviewProvider::class);
            }

            return app(FakeAIReviewProvider::class);
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
