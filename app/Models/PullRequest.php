<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['repository_id', 'number', 'source_url'])]
class PullRequest extends Model
{
    /**
     * @return BelongsTo<GitHubRepository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'repository_id');
    }

    /**
     * @return HasMany<ReviewRun, $this>
     */
    public function reviewRuns(): HasMany
    {
        return $this->hasMany(ReviewRun::class);
    }
}
