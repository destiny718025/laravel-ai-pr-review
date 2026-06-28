<?php

namespace App\Services\AI;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;

class FakeAIReviewProvider implements AIReviewProvider
{
    public function __construct(private readonly ?string $fixturePath = null) {}

    public function review(AIReviewRequest $request): string
    {
        $path = $this->fixturePath ?: base_path('tests/Fixtures/AI/fake-review-valid.json');

        return (string) file_get_contents($path);
    }
}
