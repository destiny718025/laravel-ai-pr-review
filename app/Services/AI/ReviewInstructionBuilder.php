<?php

namespace App\Services\AI;

class ReviewInstructionBuilder
{
    public function buildDefault(): string
    {
        return implode("\n", [
            'Review this GitHub pull request for actionable code review findings.',
            'Prioritize bug and security findings first.',
            'Include performance or maintainability findings when they are warranted.',
            'Include style findings only when they are useful and not noisy.',
            'Use only these severity labels: critical, high, medium, low.',
            'Use only these category labels: bug, security, performance, maintainability, style.',
            'Return exactly one JSON object and no surrounding prose or markdown.',
            'The only top-level key must be findings.',
            'If there are no actionable findings, return {"findings":[]}.',
            'Each finding must include severity, category, file_path, line_reference, title, rationale, and suggested_comment_text.',
            'Do not include comment draft state, approval state, or GitHub publication metadata.',
        ]);
    }

    public function buildWithCustomInstructions(?string $customInstructions): string
    {
        $default = $this->buildDefault();
        $customInstructions = $customInstructions === null ? null : trim($customInstructions);

        if ($customInstructions === null || $customInstructions === '') {
            return $default;
        }

        return $default."\n\nCustom Review Instructions:\n".$customInstructions;
    }
}
