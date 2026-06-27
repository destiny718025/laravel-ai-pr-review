<?php

namespace App\Services\GitHub;

use App\Data\GitHubPullRequestReference;

class GitHubPullRequestUrlParser
{
    public function parse(string $url): GitHubPullRequestReference|string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return 'invalid_url';
        }

        if ($parts['scheme'] !== 'https') {
            return 'invalid_url';
        }

        if (strtolower($parts['host']) !== 'github.com') {
            return 'not_github_pr_url';
        }

        $segments = array_values(array_filter(explode('/', trim($parts['path'], '/')), 'strlen'));

        if (count($segments) < 3 || $segments[2] !== 'pull') {
            return 'not_github_pr_url';
        }

        if (! isset($segments[3]) || ! ctype_digit($segments[3]) || (int) $segments[3] < 1) {
            return 'missing_pr_number';
        }

        $owner = $segments[0];
        $repositoryName = $segments[1];
        $pullRequestNumber = (int) $segments[3];

        return new GitHubPullRequestReference(
            owner: $owner,
            repositoryName: $repositoryName,
            pullRequestNumber: $pullRequestNumber,
            sourceUrl: sprintf('https://github.com/%s/%s/pull/%d', $owner, $repositoryName, $pullRequestNumber),
        );
    }
}
