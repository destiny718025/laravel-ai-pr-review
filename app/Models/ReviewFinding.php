<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'review_run_id',
    'severity',
    'category',
    'file_path',
    'line_reference',
    'title',
    'rationale',
    'suggested_comment_text',
    'superseded_at',
])]
class ReviewFinding extends Model
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
     * @return HasMany<ReviewCommentDraft, $this>
     */
    public function sourceDrafts(): HasMany
    {
        return $this->hasMany(ReviewCommentDraft::class, 'source_review_finding_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'superseded_at' => 'datetime',
        ];
    }
}
