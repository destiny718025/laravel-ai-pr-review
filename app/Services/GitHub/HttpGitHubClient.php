<?php

namespace App\Services\GitHub;

use App\Contracts\GitHub\GitHubClient;
use App\Data\GitHub\GitHubCommentPublicationResult;
use App\Data\GitHub\GitHubCommentPublicationTarget;
use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HttpGitHubClient implements GitHubClient
{
    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot
    {
        $payload = $this->request()
            ->get($this->repositoryPath($owner, $repository)."/pulls/{$pullRequestNumber}")
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new \UnexpectedValueException('GitHub pull request response must be an object.');
        }

        return PullRequestSnapshot::fromGitHubPayload($payload);
    }

    public function listPullRequestFiles(string $owner, string $repository, int $pullRequestNumber): array
    {
        $files = [];
        $page = 1;

        do {
            $response = $this->request()
                ->get($this->repositoryPath($owner, $repository)."/pulls/{$pullRequestNumber}/files", [
                    'per_page' => 100,
                    'page' => $page,
                ])
                ->throw();

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new \UnexpectedValueException('GitHub pull request files response must be an array.');
            }

            foreach ($payload as $filePayload) {
                if (! is_array($filePayload)) {
                    throw new \UnexpectedValueException('GitHub pull request file response entries must be objects.');
                }

                $files[] = PullRequestFileSnapshot::fromGitHubPayload($filePayload);
            }

            $page++;
        } while ($this->hasNextPage($response->header('Link')));

        return $files;
    }

    public function createPullRequestReviewComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult
    {
        $payload = $this->request()
            ->post(
                $this->repositoryPath($target->owner, $target->repository)."/pulls/{$target->pullRequestNumber}/comments",
                $target->toPullRequestReviewCommentPayload(),
            )
            ->throw()
            ->json();

        return $this->parsePublicationResult($payload, 'GitHub pull request review comment response must be an object.');
    }

    public function createPullRequestIssueComment(GitHubCommentPublicationTarget $target): GitHubCommentPublicationResult
    {
        $payload = $this->request()
            ->post(
                $this->repositoryPath($target->owner, $target->repository)."/issues/{$target->pullRequestNumber}/comments",
                $target->toIssueCommentPayload(),
            )
            ->throw()
            ->json();

        return $this->parsePublicationResult($payload, 'GitHub issue comment response must be an object.');
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.github.base_url', 'https://api.github.com'))
            ->accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => (string) config('services.github.api_version', '2022-11-28'),
            ]);

        $token = config('services.github.token');

        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }

    private function repositoryPath(string $owner, string $repository): string
    {
        return '/repos/'.rawurlencode($owner).'/'.rawurlencode($repository);
    }

    private function parsePublicationResult(mixed $payload, string $message): GitHubCommentPublicationResult
    {
        if (! is_array($payload)) {
            throw new \UnexpectedValueException($message);
        }

        return GitHubCommentPublicationResult::fromGitHubPayload($payload);
    }

    private function hasNextPage(?string $linkHeader): bool
    {
        return is_string($linkHeader) && str_contains($linkHeader, 'rel="next"');
    }
}
