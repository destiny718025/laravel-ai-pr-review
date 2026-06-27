<?php

namespace App\Repositories;

use App\Enums\ReviewRunStatus;
use App\Models\PullRequest;
use App\Models\ReviewRun;

class ReviewRunRepository
{
    public function createPendingForPullRequest(PullRequest $pullRequest): ReviewRun
    {
        return ReviewRun::create([
            'pull_request_id' => $pullRequest->id,
            'status' => ReviewRunStatus::Pending,
        ]);
    }
}
