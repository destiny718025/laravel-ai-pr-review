<?php

namespace Tests\Feature;

use App\Contracts\GitHub\GitHubClient;
use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;
use App\Services\GitHub\HttpGitHubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubPullRequestIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_resolves_github_client_and_returns_pull_request_metadata_from_fixture(): void
    {
        config([
            'services.github.base_url' => 'https://api.github.com',
            'services.github.api_version' => '2022-11-28',
            'services.github.token' => null,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1' => Http::response(
                $this->fixture('GitHub/pull-request.json'),
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $client = app(GitHubClient::class);

        $this->assertInstanceOf(HttpGitHubClient::class, $client);

        $snapshot = $client->getPullRequest('laravel', 'framework', 1);

        $this->assertInstanceOf(PullRequestSnapshot::class, $snapshot);
        $this->assertSame('Add fixture-driven GitHub ingestion boundary', $snapshot->title);
        $this->assertSame('open', $snapshot->state);
        $this->assertSame('abc123def4567890abc123def4567890abc12345', $snapshot->headSha);
        Http::assertSentCount(1);
    }

    public function test_client_paginates_pull_request_files_from_fixture_pages_without_live_requests(): void
    {
        config([
            'services.github.base_url' => 'https://api.github.com',
            'services.github.api_version' => '2022-11-28',
            'services.github.token' => null,
        ]);

        Http::preventStrayRequests();
        Http::fake([
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

        $files = app(GitHubClient::class)->listPullRequestFiles('laravel', 'framework', 1);

        $this->assertCount(3, $files);
        $this->assertContainsOnlyInstancesOf(PullRequestFileSnapshot::class, $files);
        $this->assertSame(['filename', 'patch', 'sha'], array_keys(get_object_vars($files[0])));
        $this->assertSame('app/Services/GitHub/HttpGitHubClient.php', $files[0]->filename);
        $this->assertSame('1111111111111111111111111111111111111111', $files[0]->sha);
        $this->assertStringContainsString('GitHubClient', $files[0]->patch);
        Http::assertSentCount(2);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/'.$path));
    }
}
