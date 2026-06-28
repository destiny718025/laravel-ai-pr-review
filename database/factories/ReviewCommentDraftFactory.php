<?php

namespace Database\Factories;

use App\Enums\ReviewCommentDraftStatus;
use App\Models\ReviewCommentDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewCommentDraft>
 */
class ReviewCommentDraftFactory extends Factory
{
    protected $model = ReviewCommentDraft::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ReviewCommentDraftStatus::Draft,
            'body' => 'Please handle this edge case before merging.',
            'file_path' => 'app/Example.php',
            'line_reference' => '42',
            'github_head_sha' => 'abc123def4567890abc123def4567890abc12345',
            'source_file_sha' => '1111111111111111111111111111111111111111',
            'stale_at' => null,
        ];
    }
}
