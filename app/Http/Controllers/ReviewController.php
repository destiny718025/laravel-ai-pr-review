<?php

namespace App\Http\Controllers;

use App\Repositories\ReviewRunRepository;
use App\Services\PullRequestIngestionService;
use App\Services\ReviewRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(ReviewRunRepository $reviewRunRepository): View
    {
        return view('reviews.index', [
            'reviewRuns' => $reviewRunRepository->recentWithPullRequestRepository(),
        ]);
    }

    public function store(Request $request, ReviewRunService $reviewRunService): RedirectResponse
    {
        $validated = $request->validate([
            'pr_url' => ['required', 'string'],
        ]);

        $result = $reviewRunService->createFromPullRequestUrl($validated['pr_url']);

        if (! $result->successful()) {
            return redirect()
                ->route('reviews.index')
                ->withInput()
                ->with('service_error_code', $result->errorCode())
                ->with('service_error_message', $result->message());
        }

        return redirect()
            ->route('reviews.show', $result->reviewRun())
            ->with('status', $result->message());
    }

    public function show(int|string $reviewRun, ReviewRunRepository $reviewRunRepository): View
    {
        return view('reviews.show', [
            'reviewRun' => $reviewRunRepository->findWithPullRequestRepositoryOrFail($reviewRun),
        ]);
    }

    public function fetch(int|string $reviewRun, PullRequestIngestionService $pullRequestIngestionService): RedirectResponse
    {
        $result = $pullRequestIngestionService->fetch($reviewRun);

        if (! $result->successful()) {
            return redirect()
                ->route('reviews.show', $result->reviewRun())
                ->with('service_error_code', $result->errorCode())
                ->with('service_error_message', $result->message());
        }

        return redirect()
            ->route('reviews.show', $result->reviewRun())
            ->with('status', $result->message());
    }
}
