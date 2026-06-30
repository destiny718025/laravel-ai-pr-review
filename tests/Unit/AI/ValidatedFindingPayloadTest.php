<?php

namespace Tests\Unit\AI;

use App\Data\AI\ValidatedFindingPayload;
use App\Services\AI\AIReviewPayloadValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidatedFindingPayloadTest extends TestCase
{
    public function test_validator_returns_validated_finding_payloads(): void
    {
        $findings = app(AIReviewPayloadValidator::class)->validate([
            'findings' => [
                [
                    'severity' => 'critical',
                    'category' => 'security',
                    'file_path' => 'app/Http/Controllers/ReviewController.php',
                    'line_reference' => '31',
                    'title' => 'Unsafe review finding',
                    'rationale' => 'The current flow can leak unsafe data.',
                    'suggested_comment_text' => 'Please sanitize provider errors before persisting them.',
                ],
            ],
        ]);

        $this->assertCount(1, $findings);
        $this->assertContainsOnlyInstancesOf(ValidatedFindingPayload::class, $findings);
        $this->assertSame('critical', $findings[0]->severity);
        $this->assertSame('security', $findings[0]->category);
        $this->assertSame('31', $findings[0]->lineReference);
    }

    public function test_validator_allows_nullable_line_reference(): void
    {
        $findings = app(AIReviewPayloadValidator::class)->validate([
            'findings' => [
                [
                    'severity' => 'low',
                    'category' => 'style',
                    'file_path' => 'resources/views/reviews/show.blade.php',
                    'line_reference' => null,
                    'title' => 'Useful style note',
                    'rationale' => 'The wording can be clearer.',
                    'suggested_comment_text' => 'Please make this copy more direct.',
                ],
            ],
        ]);

        $this->assertNull($findings[0]->lineReference);
    }

    public function test_validator_allows_empty_findings_array(): void
    {
        $findings = app(AIReviewPayloadValidator::class)->validate([
            'findings' => [],
        ]);

        $this->assertSame([], $findings);
    }

    public function test_validator_normalizes_common_ai_output_variants(): void
    {
        $findings = app(AIReviewPayloadValidator::class)->validate([
            'findings' => [
                [
                    'severity' => ' Medium ',
                    'category' => ' Bug ',
                    'path' => 'app/Example.php',
                    'line' => 42,
                    'title' => ' Variant schema ',
                    'reason' => ' Model used a common rationale alias. ',
                    'comment' => ' Please handle common provider schema variants. ',
                ],
            ],
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('medium', $findings[0]->severity);
        $this->assertSame('bug', $findings[0]->category);
        $this->assertSame('app/Example.php', $findings[0]->filePath);
        $this->assertSame('42', $findings[0]->lineReference);
        $this->assertSame('Variant schema', $findings[0]->title);
        $this->assertSame('Model used a common rationale alias.', $findings[0]->rationale);
        $this->assertSame('Please handle common provider schema variants.', $findings[0]->suggestedCommentText);
    }

    public function test_validator_rejects_unknown_vocabulary_and_unexpected_structure(): void
    {
        $this->expectException(ValidationException::class);

        app(AIReviewPayloadValidator::class)->validate([
            'findings' => [
                [
                    'severity' => 'urgent',
                    'category' => 'misc',
                    'file_path' => 'app/Example.php',
                    'line_reference' => null,
                    'title' => 'Bad vocabulary',
                    'rationale' => 'Invalid schema.',
                    'suggested_comment_text' => 'This should fail.',
                    'extra' => 'unexpected',
                ],
            ],
            'raw' => 'unexpected',
        ]);
    }

    public function test_validator_requires_the_full_finding_field_set(): void
    {
        $this->expectException(ValidationException::class);

        app(AIReviewPayloadValidator::class)->validate([
            'findings' => [
                [
                    'severity' => 'medium',
                    'category' => 'bug',
                    'file_path' => 'app/Example.php',
                    'title' => 'Missing fields',
                ],
            ],
        ]);
    }
}
