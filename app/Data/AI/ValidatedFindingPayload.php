<?php

namespace App\Data\AI;

readonly class ValidatedFindingPayload
{
    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public const CATEGORIES = ['bug', 'security', 'performance', 'maintainability', 'style'];

    public function __construct(
        public string $severity,
        public string $category,
        public string $filePath,
        public ?string $lineReference,
        public string $title,
        public string $rationale,
        public string $suggestedCommentText,
    ) {}

    /**
     * @param  array{
     *     severity: string,
     *     category: string,
     *     file_path: string,
     *     line_reference?: ?string,
     *     title: string,
     *     rationale: string,
     *     suggested_comment_text: string
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            severity: $payload['severity'],
            category: $payload['category'],
            filePath: $payload['file_path'],
            lineReference: $payload['line_reference'] ?? null,
            title: $payload['title'],
            rationale: $payload['rationale'],
            suggestedCommentText: $payload['suggested_comment_text'],
        );
    }

    /**
     * @return array{
     *     severity: string,
     *     category: string,
     *     file_path: string,
     *     line_reference: ?string,
     *     title: string,
     *     rationale: string,
     *     suggested_comment_text: string
     * }
     */
    public function toDatabaseArray(): array
    {
        return [
            'severity' => $this->severity,
            'category' => $this->category,
            'file_path' => $this->filePath,
            'line_reference' => $this->lineReference,
            'title' => $this->title,
            'rationale' => $this->rationale,
            'suggested_comment_text' => $this->suggestedCommentText,
        ];
    }
}
