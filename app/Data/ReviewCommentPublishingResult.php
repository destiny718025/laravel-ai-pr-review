<?php

namespace App\Data;

use App\Models\ReviewRun;

readonly class ReviewCommentPublishingResult
{
    public function __construct(
        public ReviewRun $reviewRun,
        public string $mode,
        public int $attemptedCount,
        public int $publishedCount,
        public int $failedCount,
    ) {}
}
