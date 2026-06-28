<?php

namespace App\Contracts\AI;

use App\Data\AI\AIReviewRequest;

interface AIReviewProvider
{
    public function review(AIReviewRequest $request): string;
}
