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
        $payload = $this->normalizePayload($payload);

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
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (! isset($payload['findings']) || ! is_array($payload['findings'])) {
            return $payload;
        }

        $payload['findings'] = array_map(
            fn (mixed $finding): mixed => is_array($finding) ? $this->normalizeFinding($finding) : $finding,
            $payload['findings'],
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    private function normalizeFinding(array $finding): array
    {
        $this->copyAlias($finding, 'file_path', ['file', 'path', 'filename']);
        $this->copyAlias($finding, 'line_reference', ['line', 'line_number', 'lineNumber']);
        $this->copyAlias($finding, 'rationale', ['reason', 'description']);
        $this->copyAlias($finding, 'suggested_comment_text', ['suggested_comment', 'comment', 'suggestion']);

        foreach (['severity', 'category'] as $key) {
            if (is_scalar($finding[$key] ?? null)) {
                $finding[$key] = strtolower(trim((string) $finding[$key]));
            }
        }

        foreach (['file_path', 'line_reference', 'title', 'rationale', 'suggested_comment_text'] as $key) {
            if (array_key_exists($key, $finding) && is_scalar($finding[$key])) {
                $finding[$key] = trim((string) $finding[$key]);
            }
        }

        if (($finding['line_reference'] ?? null) === '') {
            $finding['line_reference'] = null;
        }

        return $finding;
    }

    /**
     * @param  array<string, mixed>  $finding
     * @param  list<string>  $aliases
     */
    private function copyAlias(array &$finding, string $target, array $aliases): void
    {
        if (array_key_exists($target, $finding)) {
            return;
        }

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $finding)) {
                $finding[$target] = $finding[$alias];
                unset($finding[$alias]);

                return;
            }
        }
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
