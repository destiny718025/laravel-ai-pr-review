<?php

namespace App\Models;

use App\Enums\ReviewRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pull_request_id',
    'status',
    'safe_error_message',
    'github_title',
    'github_state',
    'github_head_sha',
    'github_fetched_at',
    'queued_at',
    'started_at',
    'completed_at',
    'failed_at',
    'cancelled_at',
])]
class ReviewRun extends Model
{
    /**
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * @return HasMany<ReviewRunFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ReviewRunFile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewRunStatus::class,
            'github_fetched_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
