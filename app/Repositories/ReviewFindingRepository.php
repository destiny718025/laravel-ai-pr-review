<?php

namespace App\Repositories;

use App\Data\AI\ValidatedFindingPayload;
use App\Models\ReviewFinding;
use App\Models\ReviewRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReviewFindingRepository
{
    public function supersedeCurrentForReviewRun(ReviewRun $reviewRun): int
    {
        return $reviewRun->currentFindings()->update([
            'superseded_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, ValidatedFindingPayload>  $findings
     */
    public function storeCurrentForReviewRun(ReviewRun $reviewRun, array $findings): void
    {
        DB::transaction(function () use ($reviewRun, $findings): void {
            foreach ($findings as $finding) {
                $reviewRun->findings()->create([
                    ...$finding->toDatabaseArray(),
                    'superseded_at' => null,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, ReviewFinding>
     */
    public function currentWithoutDrafts(ReviewRun $reviewRun): Collection
    {
        return $reviewRun->currentFindings()
            ->whereDoesntHave('sourceDrafts')
            ->orderBy('id')
            ->get();
    }
}
