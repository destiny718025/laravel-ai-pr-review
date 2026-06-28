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
            'stale_at' => 'datetime',
        ];
    }
}
