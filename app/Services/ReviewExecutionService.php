<?php

namespace App\Services;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Models\ReviewRun;
use App\Repositories\ReviewCommentDraftRepository;
use App\Repositories\ReviewFindingRepository;
use App\Repositories\ReviewInstructionSettingRepository;
use App\Repositories\ReviewRunRepository;
use App\Services\AI\AIReviewFailureMapper;
use App\Services\AI\AIReviewPayloadValidator;
use App\Services\AI\ReviewInstructionBuilder;
use Illuminate\Support\Facades\DB;

class ReviewExecutionService
{
    public function __construct(
        private readonly ReviewRunRepository $reviewRuns,
        private readonly ReviewFindingRepository $findings,
        private readonly ReviewCommentDraftRepository $drafts,
        private readonly ReviewInstructionSettingRepository $instructionSettings,
        private readonly AIReviewProvider $provider,
        private readonly AIReviewPayloadValidator $validator,
        private readonly AIReviewFailureMapper $failureMapper,
        private readonly ReviewInstructionBuilder $instructionBuilder,
    ) {}

    public function execute(int $reviewRunId): void
    {
        $reviewRun = $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRunId);
        $reviewRun = $this->reviewRuns->markRunning($reviewRun);

        try {
            $payload = json_decode(
                $this->provider->review($this->makeRequest($reviewRun)),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($payload)) {
                throw new \UnexpectedValueException('AI review payload must be an object.');
            }

            $validatedFindings = $this->validator->validate($payload);

            DB::transaction(function () use ($reviewRun, $validatedFindings): void {
                $this->drafts->markStaleForReviewRun($reviewRun);
                $this->findings->supersedeCurrentForReviewRun($reviewRun);
                $this->findings->storeCurrentForReviewRun($reviewRun, $validatedFindings);
                $this->reviewRuns->markCompleted($reviewRun);
            });
        } catch (\Throwable $throwable) {
            $failure = $this->failureMapper->map($throwable);

            $this->reviewRuns->markExecutionFailed($reviewRun, $failure->message);
        }
    }

    private function makeRequest(ReviewRun $reviewRun): AIReviewRequest
    {
        $reviewRun->loadMissing('files', 'pullRequest.repository');
        $pullRequest = $reviewRun->pullRequest;
        $repository = $pullRequest->repository;

        return new AIReviewRequest(
            repositoryFullName: $repository->full_name,
            pullRequestNumber: $pullRequest->number,
            sourceUrl: $pullRequest->source_url,
            headSha: (string) $reviewRun->github_head_sha,
            title: (string) $reviewRun->github_title,
            changedFiles: $reviewRun->files
                ->map(fn ($file): array => [
                    'filename' => $file->filename,
                    'patch' => $file->patch,
                    'sha' => $file->sha,
                ])
                ->values()
                ->all(),
            instructions: $this->instructionBuilder->buildWithCustomInstructions(
                $this->instructionSettings->findGlobal()?->custom_instructions,
            ),
        );
    }
}
