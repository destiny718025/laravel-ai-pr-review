<?php

namespace Tests\Unit\GitHub;

use App\Contracts\GitHub\GitHubClient;
use App\Data\GitHub\GitHubCommentPublicationResult;
use App\Data\GitHub\GitHubCommentPublicationTarget;
use App\Services\GitHub\HttpGitHubClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpGitHubClientPublicationTest extends TestCase
{
    public function test_client_posts_line_level_pull_request_review_comments_without_live_requests(): void
    {
        config([
            'services.github.base_url' => 'https://api.github.com',
            'services.github.api_version' => '2022-11-28',
            'services.github.token' => 'test-token',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/pulls/1/comments' => Http::response(
                $this->fixture('GitHub/pull-request-review-comment.json'),
                201,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $client = app(GitHubClient::class);

        $this->assertInstanceOf(HttpGitHubClient::class, $client);

        $result = $client->createPullRequestReviewComment(new GitHubCommentPublicationTarget(
            owner: 'laravel',
            repository: 'framework',
            pullRequestNumber: 1,
            body: 'This needs a null check before array access.',
            path: 'app/Services/GitHub/HttpGitHubClient.php',
            line: 27,
            commitSha: 'abc123def4567890abc123def4567890abc12345',
        ));

        $this->assertInstanceOf(GitHubCommentPublicationResult::class, $result);
        $this->assertSame(['id', 'htmlUrl', 'postedAt'], array_keys(get_object_vars($result)));
        $this->assertSame('123456789', $result->id);
        $this->assertSame('https://github.com/laravel/framework/pull/1#discussion_r123456789', $result->htmlUrl);
        $this->assertSame('2026-06-29T01:02:03Z', $result->postedAt->toISOString());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.github.com/repos/laravel/framework/pulls/1/comments'
                && $request->method() === 'POST'
                && $request['body'] === 'This needs a null check before array access.'
                && $request['commit_id'] === 'abc123def4567890abc123def4567890abc12345'
                && $request['path'] === 'app/Services/GitHub/HttpGitHubClient.php'
                && $request['line'] === 27
                && $request['side'] === 'RIGHT';
        });
        Http::assertSentCount(1);
    }

    public function test_client_posts_fallback_issue_comments_without_live_requests(): void
    {
        config([
            'services.github.base_url' => 'https://api.github.com',
            'services.github.api_version' => '2022-11-28',
            'services.github.token' => 'test-token',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/laravel/framework/issues/1/comments' => Http::response(
                $this->fixture('GitHub/pull-request-issue-comment.json'),
                201,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = app(GitHubClient::class)->createPullRequestIssueComment(new GitHubCommentPublicationTarget(
            owner: 'laravel',
            repository: 'framework',
            pullRequestNumber: 1,
            body: 'Fallback draft body.',
        ));

        $this->assertInstanceOf(GitHubCommentPublicationResult::class, $result);
        $this->assertSame(['id', 'htmlUrl', 'postedAt'], array_keys(get_object_vars($result)));
        $this->assertSame('987654321', $result->id);
        $this->assertSame('https://github.com/laravel/framework/pull/1#issuecomment-987654321', $result->htmlUrl);
        $this->assertSame('2026-06-29T01:02:03Z', $result->postedAt->toISOString());

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.github.com/repos/laravel/framework/issues/1/comments'
                && $request->method() === 'POST'
                && $data === ['body' => 'Fallback draft body.'];
        });
        Http::assertSentCount(1);
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/'.$path));
    }
}
