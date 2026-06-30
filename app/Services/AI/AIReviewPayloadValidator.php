<?php

namespace App\Services\AI;

use App\Data\AI\ValidatedFindingPayload;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AIReviewPayloadValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, ValidatedFindingPayload>
     *
     * @throws ValidationException
     */
    public function validate(array $payload): array
    {
        $this->rejectUnknownTopLevelKeys($payload);
        $this->rejectUnknownFindingKeys($payload);

        $validator = Validator::make($payload, [
            'findings' => ['present', 'array'],
            'findings.*' => ['required', 'array'],
            'findings.*.severity' => ['required', 'string', Rule::in(ValidatedFindingPayload::SEVERITIES)],
            'findings.*.category' => ['required', 'string', Rule::in(ValidatedFindingPayload::CATEGORIES)],
            'findings.*.file_path' => ['required', 'string'],
            'findings.*.line_reference' => ['nullable', 'string'],
            'findings.*.title' => ['required', 'string'],
            'findings.*.rationale' => ['required', 'string'],
            'findings.*.suggested_comment_text' => ['required', 'string'],
        ]);

        $validated = $validator->validate();

        return array_map(
            fn (array $finding): ValidatedFindingPayload => ValidatedFindingPayload::fromArray($finding),
            $validated['findings'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function rejectUnknownTopLevelKeys(array $payload): void
    {
        $unexpected = array_diff(array_keys($payload), ['findings']);

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'payload' => 'AI review payload contains unexpected top-level fields.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function rejectUnknownFindingKeys(array $payload): void
    {
        if (! isset($payload['findings']) || ! is_array($payload['findings'])) {
            return;
        }

        $allowed = [
            'severity',
            'category',
            'file_path',
            'line_reference',
            'title',
            'rationale',
            'suggested_comment_text',
        ];

        foreach ($payload['findings'] as $index => $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $unexpected = array_diff(array_keys($finding), $allowed);

            if ($unexpected !== []) {
                throw ValidationException::withMessages([
                    "findings.{$index}" => 'AI review finding contains unexpected fields.',
                ]);
            }
        }
    }
}
