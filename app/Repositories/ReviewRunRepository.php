<?php

namespace App\Repositories;

use App\Enums\ReviewRunStatus;
use App\Models\PullRequest;
use App\Models\ReviewRun;
use Illuminate\Database\Eloquent\Collection;

class ReviewRunRepository
{
    public function createPendingForPullRequest(PullRequest $pullRequest): ReviewRun
    {
        return ReviewRun::create([
            'pull_request_id' => $pullRequest->id,
            'status' => ReviewRunStatus::Pending,
        ]);
    }

    /**
     * @return Collection<int, ReviewRun>
     */
    public function recentWithPullRequestRepository(int $limit = 25): Collection
    {
        return ReviewRun::query()
            ->with('pullRequest.repository')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findWithPullRequestRepositoryOrFail(int|string $id): ReviewRun
    {
        return ReviewRun::query()
            ->with('pullRequest.repository')
            ->findOrFail($id);
    }
}
