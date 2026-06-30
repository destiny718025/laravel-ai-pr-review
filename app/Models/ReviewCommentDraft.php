<?php

namespace App\Models;

use App\Enums\ReviewCommentDraftStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'review_run_id',
    'source_review_finding_id',
    'status',
    'body',
    'file_path',
    'line_reference',
    'github_head_sha',
    'source_file_sha',
    'github_comment_id',
    'github_comment_html_url',
    'posted_at',
    'publication_error_code',
    'publication_error_message',
    'stale_at',
])]
class ReviewCommentDraft extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<ReviewRun, $this>
     */
    public function reviewRun(): BelongsTo
    {
        return $this->belongsTo(ReviewRun::class);
    }

    /**
     * @return BelongsTo<ReviewFinding, $this>
     */
    public function sourceFinding(): BelongsTo
    {
        return $this->belongsTo(ReviewFinding::class, 'source_review_finding_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewCommentDraftStatus::class,
            'posted_at' => 'datetime',
            'stale_at' => 'datetime',
        ];
    }

    public function hasSufficientLineLevelTarget(): bool
    {
        return $this->body !== ''
            && $this->file_path !== ''
            && $this->github_head_sha !== ''
            && $this->lineNumber() !== null;
    }

    public function lineNumber(): ?int
    {
        if ($this->line_reference === null || $this->line_reference === '') {
            return null;
        }

        $line = filter_var($this->line_reference, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $line === false ? null : $line;
    }
}
