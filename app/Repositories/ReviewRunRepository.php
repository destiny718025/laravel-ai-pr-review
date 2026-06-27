<?php

namespace App\Repositories;

use App\Data\GitHub\PullRequestFileSnapshot;
use App\Data\GitHub\PullRequestSnapshot;
use App\Enums\ReviewRunStatus;
use App\Models\PullRequest;
use App\Models\ReviewRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            ->with(['files', 'pullRequest.repository'])
            ->findOrFail($id);
    }

    /**
     * @param  array<int, PullRequestFileSnapshot>  $files
     */
    public function storeGitHubSnapshot(ReviewRun $reviewRun, PullRequestSnapshot $snapshot, array $files): ReviewRun
    {
        return DB::transaction(function () use ($reviewRun, $snapshot, $files): ReviewRun {
            $reviewRun->forceFill([
                'status' => ReviewRunStatus::Pending,
                'github_title' => $snapshot->title,
                'github_state' => $snapshot->state,
                'github_head_sha' => $snapshot->headSha,
                'github_fetched_at' => now(),
                'safe_error_message' => null,
                'failed_at' => null,
            ])->save();

            $reviewRun->files()->delete();

            foreach ($files as $file) {
                $reviewRun->files()->create([
                    'filename' => $file->filename,
                    'patch' => $file->patch,
                    'sha' => $file->sha,
                ]);
            }

            return $reviewRun->refresh()->load(['files', 'pullRequest.repository']);
        });
    }

    public function markGitHubFetchFailed(ReviewRun $reviewRun, string $safeErrorMessage): ReviewRun
    {
        $reviewRun->forceFill([
            'status' => ReviewRunStatus::Failed,
            'safe_error_message' => $safeErrorMessage,
            'failed_at' => now(),
        ])->save();

        return $reviewRun->refresh()->load(['files', 'pullRequest.repository']);
    }
}
