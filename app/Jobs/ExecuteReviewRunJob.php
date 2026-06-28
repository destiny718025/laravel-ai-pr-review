<?php

namespace App\Jobs;

use App\Services\ReviewExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteReviewRunJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reviewRunId) {}

    public function handle(ReviewExecutionService $reviewExecutionService): void
    {
        $reviewExecutionService->execute($this->reviewRunId);
    }
}
