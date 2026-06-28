<?php

namespace App\Repositories;

use App\Data\AI\ValidatedFindingPayload;
use App\Models\ReviewRun;
use Illuminate\Support\Facades\DB;

class ReviewFindingRepository
{
    /**
     * @param  array<int, ValidatedFindingPayload>  $findings
     */
    public function replaceForReviewRun(ReviewRun $reviewRun, array $findings): void
    {
        DB::transaction(function () use ($reviewRun, $findings): void {
            $reviewRun->findings()->delete();

            foreach ($findings as $finding) {
                $reviewRun->findings()->create($finding->toDatabaseArray());
            }
        });
    }
}
