<?php

namespace Database\Factories;

use App\Models\ReviewFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewFinding>
 */
class ReviewFindingFactory extends Factory
{
    protected $model = ReviewFinding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'severity' => 'medium',
            'category' => 'bug',
            'file_path' => 'app/Example.php',
            'line_reference' => '42',
            'title' => 'Example review finding',
            'rationale' => 'The code can fail under a realistic edge case.',
            'suggested_comment_text' => 'Please handle this edge case before merging.',
            'superseded_at' => null,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => [
            'superseded_at' => null,
        ]);
    }

    public function superseded(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn (): array => [
            'superseded_at' => $at ?? now(),
        ]);
    }
}
