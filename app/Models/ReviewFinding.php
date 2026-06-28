<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'review_run_id',
    'severity',
    'category',
    'file_path',
    'line_reference',
    'title',
    'rationale',
    'suggested_comment_text',
])]
class ReviewFinding extends Model
{
    /**
     * @return BelongsTo<ReviewRun, $this>
     */
    public function reviewRun(): BelongsTo
    {
        return $this->belongsTo(ReviewRun::class);
    }
}
